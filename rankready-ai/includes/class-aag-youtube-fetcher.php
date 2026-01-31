<?php

if (!defined('ABSPATH')) {
    exit;
}

class AAG_Youtube_Fetcher
{
    /**
     * Get videos from a specific channel using YouTube Data API
     * 
     * @param string $channel_id_or_url The Channel ID or URL
     * @param string $api_key YouTube Data API Key
     * @param int $max_results Number of videos to fetch (default 10)
     * @return array|WP_Error Array of video URLs or WP_Error on failure
     */
    public function get_channel_videos($channel_id_or_url, $api_key, $max_results = 10)
    {
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'YouTube API Key is required.');
        }

        // 1. Resolve Channel ID if URL is provided
        $channel_id = $this->resolve_channel_id($channel_id_or_url, $api_key);
        if (is_wp_error($channel_id)) {
            return $channel_id;
        }

        // 2. Get Uploads Playlist ID
        $api_url = "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id={$channel_id}&key={$api_key}";
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return new WP_Error('api_error', 'YouTube API returned error: ' . $code);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['items'])) {
            return new WP_Error('channel_not_found', 'Channel not found or has no content.');
        }

        $uploads_playlist_id = $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];

        // 3. Get Videos from Uploads Playlist
        $playlist_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId={$uploads_playlist_id}&maxResults={$max_results}&key={$api_key}";
        $playlist_response = wp_remote_get($playlist_url);

        if (is_wp_error($playlist_response)) {
            return $playlist_response;
        }

        $playlist_data = json_decode(wp_remote_retrieve_body($playlist_response), true);

        $video_urls = [];
        if (!empty($playlist_data['items'])) {
            foreach ($playlist_data['items'] as $item) {
                $video_id = $item['snippet']['resourceId']['videoId'];
                $video_urls[] = "https://www.youtube.com/watch?v={$video_id}";
            }
        }

        return $video_urls;
    }

    /**
     * Helper to extract Channel ID from input
     */
    private function resolve_channel_id($input, $api_key)
    {
        // If it looks like a channel ID (starts with UC and is 24 chars)
        if (preg_match('/^UC[\w-]{22}$/', $input)) {
            return $input;
        }

        // Handle full URLs
        if (strpos($input, 'youtube.com/channel/') !== false) {
            $parts = explode('/channel/', $input);
            return explode('/', end($parts))[0]; // Handle trailing slash
        }

        // Handle handle/custom URLs (requires API lookup)
        $handle = $input;
        if (strpos($input, 'youtube.com/') !== false) {
            $path = parse_url($input, PHP_URL_PATH);
            $handle = trim($path, '/');
        }

        // Remove @ if present
        $handle = ltrim($handle, '@');

        // Look up channel by handle using search endpoint (limited but works often) or channels endpoint if forUsername (deprecated mostly but legacy works)
        // Best modern way: search for type=channel&q=handle
        $search_url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&q=" . urlencode($handle) . "&key={$api_key}&maxResults=1";

        $response = wp_remote_get($search_url);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['items'])) {
            return $data['items'][0]['id']['channelId'];
        }

        return new WP_Error('resolve_failed', 'Could not resolve Channel ID.');
    }

    /**
     * Attempt to fetch transcript/captions from a video page.
     * 
     * @param string $video_url
     * @return string|WP_Error The transcript text or error.
     */
    public function get_transcript($video_url)
    {
        $video_html = $this->fetch_url_content($video_url);

        if (is_wp_error($video_html)) {
            return $video_html;
        }

        $caption_tracks = [];

        // Attempt 1: Extract ytInitialPlayerResponse using recursive regex (handles nested braces)
        // Pattern matches: ytInitialPlayerResponse = { ...balanced... }
        if (preg_match('/ytInitialPlayerResponse\s*=\s*(\{((?>[^{}]+)|(?1))*\})/s', $video_html, $matches)) {
            $json_str = $matches[1];
            $player_data = json_decode($json_str, true);

            if ($player_data && isset($player_data['captions']['playerCaptionsTracklistRenderer']['captionTracks'])) {
                $caption_tracks = $player_data['captions']['playerCaptionsTracklistRenderer']['captionTracks'];
            }
        }

        // Attempt 2: Fallback logic - Extract captionTracks directly if Attempt 1 failed
        if (empty($caption_tracks)) {
            // This regex tries to match the specific "captionTracks": [ ... ] structure
            // We use a slightly more greedy approach but stop at "]" followed by "}" or "," to try and capture the list
            if (preg_match('/"captionTracks":\s*(\[.+?\])\s*(?:,|}|"audioTracks")/s', $video_html, $matches)) {
                $caption_tracks = json_decode($matches[1], true);
            }
        }

        // Attempt 2b: Even looser fallback
        if (empty($caption_tracks)) {
            if (preg_match('/"captionTracks":\s*(\[\{.*?\}\])/', $video_html, $matches)) {
                $caption_tracks = json_decode($matches[1], true);
            }
        }

        if (empty($caption_tracks)) {
            $snippet = substr(strip_tags($video_html), 0, 200); // Debug snippet
            return new WP_Error('no_captions', 'No captions found. Debug: ' . $snippet);
        }

        // Strategy:
        // 1. Look for explicit English ('en')
        // 2. Look for auto-generated English ('en' and kind='asr')
        // 3. Look for any English ('en-US', 'en-GB')
        // 4. Fallback to first available

        $selected_track = null;

        // 1. Explicit English
        foreach ($caption_tracks as $track) {
            if ($track['languageCode'] === 'en' && (!isset($track['kind']) || $track['kind'] !== 'asr')) {
                $selected_track = $track;
                break;
            }
        }

        // 2. Auto-generated English
        if (!$selected_track) {
            foreach ($caption_tracks as $track) {
                if ($track['languageCode'] === 'en' && isset($track['kind']) && $track['kind'] === 'asr') {
                    $selected_track = $track;
                    break;
                }
            }
        }

        // 3. Any English
        if (!$selected_track) {
            foreach ($caption_tracks as $track) {
                if (strpos($track['languageCode'], 'en') === 0) {
                    $selected_track = $track;
                    break;
                }
            }
        }

        // 4. First available
        if (!$selected_track) {
            $selected_track = $caption_tracks[0];
        }

        $transcript_url = $selected_track['baseUrl'];

        // Fetch XML transcript
        $transcript_xml = $this->fetch_url_content($transcript_url);
        if (is_wp_error($transcript_xml)) {
            return $transcript_xml;
        }

        // Parse XML to text
        // Format: <text start="0.04" dur="1.5">Hello world</text>
        // Note: Sometimes the URL returns JSON3 format if fmt=json3 is appended, but default is usually XML. 
        // We will assume XML or basic parsing.

        $full_text = '';

        // Check if it's XML
        if (strpos($transcript_xml, '<text') !== false) {
            preg_match_all('/<text[^>]*>(.*?)<\/text>/s', $transcript_xml, $matches);
            if (!empty($matches[1])) {
                $full_text = implode(' ', $matches[1]);
            }
        }
        // Fallback: Check if it's JSON (sometimes happens with ASR or specific URLs)
        elseif (strpos($transcript_xml, '"events"') !== false) {
            $json_transcript = json_decode($transcript_xml, true);
            if ($json_transcript && isset($json_transcript['events'])) {
                foreach ($json_transcript['events'] as $event) {
                    if (isset($event['segs'])) {
                        foreach ($event['segs'] as $seg) {
                            if (isset($seg['utf8'])) {
                                $full_text .= $seg['utf8'] . ' ';
                            }
                        }
                    }
                }
            }
        }

        if (empty($full_text)) {
            // Second fallback for XML if simple check failed but tags exist
            preg_match_all('/<text[^>]*>(.*?)<\/text>/s', $transcript_xml, $matches);
            if (!empty($matches[1])) {
                $full_text = implode(' ', $matches[1]);
            } else {
                return new WP_Error('parse_error', 'Could not parse transcript (XML or JSON).');
            }
        }

        // HTML Decode (e.g. &#39; -> ')
        $full_text = html_entity_decode($full_text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        // Basic cleanup
        $full_text = str_replace(["\n", "\r"], " ", $full_text);
        $full_text = preg_replace('/\s+/', ' ', $full_text);

        return trim($full_text);
    }

    private function fetch_url_content($url)
    {
        $args = [
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'headers' => [
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cookie' => 'CONSENT=YES+; PENDING_G_DATA_CONSENT=YES+;',
            ],
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        return wp_remote_retrieve_body($response);
    }
}
