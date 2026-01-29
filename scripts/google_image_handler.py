"""Fetch images from Google Custom Search API (Legal and Recommended)"""
import requests
from PIL import Image
from io import BytesIO
import time
import random
import os


class GoogleImageSearchHandler:
    """
    Uses Google Custom Search API - LEGAL and RECOMMENDED
    
    Setup:
    1. Go to https://console.cloud.google.com/apis/credentials
    2. Create API key (enable Custom Search API)
    3. Go to https://programmablesearchengine.google.com/
    4. Create Custom Search Engine
    5. Enable "Image Search" and "Search the entire web"
    6. Get your Search Engine ID
    7. Add to GitHub Secrets:
       - GOOGLE_SEARCH_API_KEY
       - GOOGLE_SEARCH_ENGINE_ID
    """
    
    def __init__(self, api_key=None, search_engine_id=None):
        # Get from environment if not provided
        self.api_key = api_key or os.environ.get('GOOGLE_SEARCH_API_KEY')
        self.search_engine_id = search_engine_id or os.environ.get('GOOGLE_SEARCH_ENGINE_ID')
        self.base_url = "https://www.googleapis.com/customsearch/v1"
        
        if not self.api_key or not self.search_engine_id:
            raise ValueError(
                "Google Custom Search credentials not found!\n"
                "Please set GOOGLE_SEARCH_API_KEY and GOOGLE_SEARCH_ENGINE_ID environment variables.\n"
                "Get them from:\n"
                "- API Key: https://console.cloud.google.com/apis/credentials\n"
                "- Search Engine ID: https://programmablesearchengine.google.com/"
            )
    
    def search_images(self, query, num_images=10, image_size='large'):
        """
        Search images using Google Custom Search API
        
        Args:
            query: Search query
            num_images: Number of images to fetch (max 10 per request)
            image_size: 'large', 'medium', or 'small'
            
        Returns:
            List of image data dicts
        """
        
        print(f"🔍 Searching Google for: {query}")
        
        try:
            params = {
                'key': self.api_key,
                'cx': self.search_engine_id,
                'q': query,
                'searchType': 'image',
                'num': min(num_images, 10),  # Max 10 per request
                'imgSize': image_size,
                'safe': 'active',
                'fileType': 'jpg,png,webp',
                'imgType': 'photo'  # Focus on photos, not clipart
            }
            
            response = requests.get(self.base_url, params=params, timeout=15)
            response.raise_for_status()
            
            data = response.json()
            
            if 'items' not in data:
                print(f"⚠️ No images found for query: {query}")
                return []
            
            image_results = []
            for item in data.get('items', []):
                image_results.append({
                    'url': item['link'],
                    'thumbnail': item.get('image', {}).get('thumbnailLink'),
                    'title': item.get('title', 'Untitled'),
                    'source': item.get('displayLink', 'Unknown'),
                    'width': item.get('image', {}).get('width', 0),
                    'height': item.get('image', {}).get('height', 0),
                })
            
            print(f"✅ Found {len(image_results)} images from Google")
            return image_results
            
        except requests.exceptions.HTTPError as e:
            if e.response.status_code == 429:
                print("❌ Google API rate limit exceeded. Wait and try again.")
            else:
                print(f"❌ Google Custom Search API error: {e}")
            return []
        except Exception as e:
            print(f"❌ Error searching Google: {e}")
            return []
    
    def download_image(self, url, max_retries=2):
        """
        Download image from URL and return PIL Image
        
        Args:
            url: Image URL
            max_retries: Number of retry attempts
            
        Returns:
            PIL Image object or None if failed
        """
        
        for attempt in range(max_retries):
            try:
                # Add delay between downloads to avoid rate limiting
                time.sleep(random.uniform(0.2, 0.6))
                
                headers = {
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept': 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'Accept-Language': 'en-US,en;q=0.9',
                    'Accept-Encoding': 'gzip, deflate, br',
                    'Referer': 'https://www.google.com/',
                    'DNT': '1',
                    'Connection': 'keep-alive',
                    'Upgrade-Insecure-Requests': '1',
                }
                
                # Shorter timeout and allow redirects
                response = requests.get(
                    url, 
                    headers=headers, 
                    timeout=10, 
                    stream=True,
                    allow_redirects=True,
                    verify=True
                )
                response.raise_for_status()
                
                # Check content type
                content_type = response.headers.get('content-type', '').lower()
                if 'image' not in content_type:
                    print(f"      ⚠️ Not an image ({content_type})")
                    return None
                
                # Check content length (skip if too small or too large)
                content_length = int(response.headers.get('content-length', 0))
                if content_length > 0:
                    if content_length < 5000:  # Less than 5KB
                        print(f"      ⚠️ Image too small ({content_length} bytes)")
                        return None
                    if content_length > 10_000_000:  # More than 10MB
                        print(f"      ⚠️ Image too large ({content_length / 1_000_000:.1f} MB)")
                        return None
                
                # Load image
                img_data = BytesIO(response.content)
                img = Image.open(img_data)
                
                # Verify image
                img.verify()
                
                # Reopen after verify (verify closes the file)
                img_data.seek(0)
                img = Image.open(img_data)
                
                # Convert to RGB if necessary
                if img.mode in ('RGBA', 'LA', 'P'):
                    background = Image.new('RGB', img.size, (255, 255, 255))
                    if img.mode == 'P':
                        img = img.convert('RGBA')
                    if img.mode in ('RGBA', 'LA'):
                        background.paste(img, mask=img.split()[-1])
                    else:
                        background.paste(img)
                    img = background
                elif img.mode != 'RGB':
                    img = img.convert('RGB')
                
                # Basic validation - ensure minimum quality (lowered to 200x200)
                if img.width < 200 or img.height < 200:
                    print(f"      ⚠️ Image too small: {img.width}x{img.height}")
                    return None
                
                # Success!
                return img
                
            except requests.exceptions.Timeout:
                print(f"      ⚠️ Timeout (attempt {attempt + 1}/{max_retries})")
                if attempt < max_retries - 1:
                    time.sleep(1)
                    continue
                return None
                
            except requests.exceptions.RequestException as e:
                print(f"      ⚠️ Request failed: {str(e)[:60]}")
                return None
                
            except Image.UnidentifiedImageError:
                print(f"      ⚠️ Invalid/corrupted image data")
                return None
                
            except Exception as e:
                print(f"      ⚠️ Error: {str(e)[:60]}")
                if attempt < max_retries - 1:
                    time.sleep(1)
                    continue
                return None
        
        return None
    
    def get_images_for_collage(self, query, num_images=4):
        """
        Get images for collage creation
        
        Args:
            query: Search query
            num_images: Number of images needed (3-4 recommended)
            
        Returns:
            List of dicts with image info compatible with collage generator
        """
        
        # Search for more images than needed (some may fail to download)
        search_count = min(num_images * 3, 10)  # Increased multiplier for better success rate
        search_results = self.search_images(query, search_count)
        
        if not search_results:
            print("❌ No images found from Google Custom Search")
            return []
        
        # Try to download images
        images_data = []
        
        for i, result in enumerate(search_results):
            if len(images_data) >= num_images:
                break
            
            print(f"📥 Downloading image {len(images_data)+1}/{num_images}...")
            
            img = self.download_image(result['url'])
            if img:
                images_data.append({
                    'url': result['url'],
                    'image': img,
                    'photographer': result.get('source', 'Google Search'),
                    'photographer_url': result['url'],
                    'source': 'google',
                    'title': result.get('title', '')
                })
                print(f"   ✅ {result.get('title', 'Untitled')[:50]}...")
        
        if len(images_data) < 2:
            print(f"⚠️ Only got {len(images_data)} images, need at least 2")
        else:
            print(f"✅ Successfully downloaded {len(images_data)} images")
        
        return images_data