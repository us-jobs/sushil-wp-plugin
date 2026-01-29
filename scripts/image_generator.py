"""Generate article-relevant collage images using Google Custom Search API"""
import os
import random
import time
from PIL import Image, ImageDraw, ImageFont
from config import IMAGE_QUALITY, IMAGE_MAX_WIDTH, IMAGE_MAX_HEIGHT, OPTIMIZE_IMAGE
from article_generator import client, TEXT_MODEL  # Import Gemini client

try:
    from google_image_handler import GoogleImageSearchHandler
    HAS_API_HANDLER = True
except ImportError:
    print("⚠️ google_image_handler not found, collage generation will fail")
    HAS_API_HANDLER = False


def extract_main_subject_from_title(title):
    """Extract main subject from title for fallback search queries
    
    Args:
        title: Article title
        
    Returns:
        List of fallback search queries
    """
    # Clean the title
    title_lower = title.lower()
    
    # Common patterns to extract person names or main subjects
    queries = []
    
    # Pattern 1: Extract person name (usually first 2-3 words)
    words = title.split()
    if len(words) >= 2:
        # Try first 2 words (often a person's name)
        potential_name = ' '.join(words[:2])
        queries.append(potential_name.lower())
        
        # Also try first 3 words if they might be a name
        if len(words) >= 3 and not words[2].lower() in ['the', 'and', 'of', 'in', 'with']:
            potential_name_3 = ' '.join(words[:3])
            queries.append(potential_name_3.lower())
    
    # Pattern 2: Look for known celebrity names
    celebrity_names = [
        'lionel messi', 'messi', 'cristiano ronaldo', 'ronaldo', 
        'neymar', 'lebron james', 'tiger woods', 'serena williams'
    ]
    
    for name in celebrity_names:
        if name in title_lower:
            queries.insert(0, name)  # Add to front
            break
    
    # Pattern 3: If no person detected, use topic keywords
    if not queries:
        # Remove common filler words
        filler_words = {'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'}
        meaningful_words = [w for w in words[:5] if w.lower() not in filler_words]
        if meaningful_words:
            queries.append(' '.join(meaningful_words[:3]).lower())
    
    # Remove duplicates and clean
    queries = list(dict.fromkeys(queries))  # Remove duplicates while preserving order
    queries = [q.strip().strip(',').strip(':') for q in queries]  # Remove trailing punctuation
    queries = [q for q in queries if len(q) >= 4]  # Minimum 4 characters
    
    return queries[:3]  # Max 3 queries


def generate_search_queries_from_title(title):
    """Use Gemini AI to generate optimal search queries based on article title
    
    Args:
        title: Article title
        
    Returns:
        List of 2-3 search queries for Google Images
    """
    
    prompt = f"""
Given this blog post title: "{title}"

Generate 2-3 Google Image search queries to find the most relevant photos for this article.

Requirements:
- Extract the main subject/person from the title (if any)
- If the title mentions specific people (mother, son, girlfriend, family), include them in the search
- Make queries specific to the article topic
- Keep queries short and focused (2-5 words each)
- Return ONLY the search queries, one per line, nothing else
- Do NOT add quotes, colons, or extra punctuation

Examples:
Title: "Lionel Messi Childhood Family: Parents, Siblings, and Support System"
lionel messi family
messi with parents
messi childhood

Title: "How Lionel Messi's Mother Shaped His Childhood and Career"
lionel messi mother
messi family
messi childhood

Title: "Cristiano Ronaldo's Training Routine"
cristiano ronaldo training
ronaldo workout

Now generate for the given title above. Return ONLY the search queries, one per line:
"""
    
    try:
        # Add a small delay to avoid rate limiting
        time.sleep(0.5)
        
        response = client.models.generate_content(
            model=TEXT_MODEL,
            contents=prompt
        )
        
        queries = [q.strip().strip(',').strip(':') for q in response.text.strip().split('\n') if q.strip()]
        queries = [q for q in queries if len(q) >= 4]  # Filter out very short queries
        queries = queries[:3]  # Max 3 queries
        
        if queries:
            print(f"🤖 Gemini generated {len(queries)} search queries:")
            for i, q in enumerate(queries, 1):
                print(f"   {i}. {q}")
            return queries
        else:
            raise ValueError("No valid queries generated")
        
    except Exception as e:
        print(f"⚠️ Gemini query generation failed: {e}")
        
        # ROBUST FALLBACK: Extract subject intelligently
        fallback_queries = extract_main_subject_from_title(title)
        
        if fallback_queries:
            print(f"✅ Using intelligent fallback queries ({len(fallback_queries)}):")
            for i, q in enumerate(fallback_queries, 1):
                print(f"   {i}. {q}")
            return fallback_queries
        else:
            # Ultimate fallback: use first 2 words
            words = title.split()[:2]
            simple_query = ' '.join(words).lower()
            print(f"⚠️ Using simple fallback: {simple_query}")
            return [simple_query]


def filter_relevant_images_with_gemini(title, image_results):
    """Use Gemini AI to filter and rank images based on article relevance
    
    Args:
        title: Article title
        image_results: List of image result dicts with 'title' and 'url'
        
    Returns:
        List of filtered and ranked image indices
    """
    
    if len(image_results) <= 4:
        # If we have 4 or fewer, use all of them
        return list(range(len(image_results)))
    
    # Prepare image descriptions for Gemini
    image_descriptions = []
    for i, img in enumerate(image_results[:10]):  # Analyze max 10 images
        desc = f"{i}. {img.get('title', 'Untitled')}"
        image_descriptions.append(desc)
    
    descriptions_text = '\n'.join(image_descriptions)
    
    prompt = f"""
Article title: "{title}"

Available images:
{descriptions_text}

Task: Select the 4 most relevant images for this article based on their descriptions.

Requirements:
- Choose images that best match the article topic
- Prioritize images showing people mentioned in the title
- Prefer action/contextual photos over generic portraits
- Return ONLY the numbers (0-9) of the 4 best images, separated by commas

Example response: 0,2,5,7

Your response (only numbers):
"""
    
    try:
        # Add delay to avoid rate limiting
        time.sleep(0.5)
        
        response = client.models.generate_content(
            model=TEXT_MODEL,
            contents=prompt
        )
        
        # Parse response
        selected = response.text.strip()
        indices = [int(x.strip()) for x in selected.split(',') if x.strip().isdigit()]
        indices = [i for i in indices if i < len(image_results)][:4]  # Max 4 images
        
        if len(indices) >= 2:
            print(f"🤖 Gemini selected {len(indices)} most relevant images: {indices}")
            return indices
        else:
            # Fallback if not enough selected
            return list(range(min(4, len(image_results))))
        
    except Exception as e:
        print(f"⚠️ Gemini filtering failed: {e}")
        # Fallback: use first 4 images
        return list(range(min(4, len(image_results))))


def generate_article_image(prompt, output_path):
    """Generate article-relevant collage using Google Custom Search API + Gemini AI
    
    Uses Gemini to:
    1. Generate optimal search queries based on article title
    2. Filter and rank the most relevant images from search results
    
    Args:
        prompt: Article title
        output_path: Where to save the collage
    
    Returns:
        True if successful, raises exception otherwise
    """
    
    if not HAS_API_HANDLER:
        raise ImportError("google_image_handler module is required for collage generation")
    
    print(f"🎨 Creating AI-powered collage with relevant images")
    print(f"📝 Article Title: {prompt[:100]}...")
    
    # Step 1: Use Gemini to generate search queries (with robust fallback)
    print(f"\n{'='*50}")
    print("Step 1: AI Query Generation")
    print("="*50)
    search_queries = generate_search_queries_from_title(prompt)
    
    # Step 2: Search Google for images with all queries
    print(f"\n{'='*50}")
    print("Step 2: Searching Google Images")
    print("="*50)
    api_handler = GoogleImageSearchHandler()
    all_image_results = []
    
    for query in search_queries:
        print(f"🔍 Searching: {query}")
        try:
            # Request 10 images per query (max allowed by Google)
            results = api_handler.search_images(query, num_images=10)
            all_image_results.extend(results)
            
            # If we have enough images, stop searching
            if len(all_image_results) >= 8:
                print(f"✅ Got enough images ({len(all_image_results)}), stopping search")
                break
                
        except Exception as e:
            print(f"⚠️ Search failed for '{query}': {e}")
            continue
    
    # If no images found, try broader searches
    if len(all_image_results) < 2:
        print(f"\n⚠️ Not enough images ({len(all_image_results)}). Trying broader searches...")
        
        # Try single-word queries
        words = prompt.split()
        broad_queries = []
        
        # Try first word (usually a name)
        if len(words) >= 1:
            broad_queries.append(words[0].lower())
        
        # Try first two words
        if len(words) >= 2:
            broad_queries.append(f"{words[0]} {words[1]}".lower())
        
        for broad_query in broad_queries:
            print(f"🔍 Trying broader search: {broad_query}")
            try:
                results = api_handler.search_images(broad_query, num_images=8)
                all_image_results.extend(results)
                
                if len(all_image_results) >= 2:
                    break
            except Exception as e:
                print(f"⚠️ Broad search failed: {e}")
                continue
    
    if len(all_image_results) < 2:
        raise Exception(f"❌ Not enough images found. Only got {len(all_image_results)} images from Google. Need at least 2.\n"
                       f"💡 Possible reasons:\n"
                       f"   - Google Custom Search API quota exceeded\n"
                       f"   - Search queries too specific\n"
                       f"   - Network/API issues")
    
    print(f"✅ Found {len(all_image_results)} total images from all searches")
    
    # Step 3: Use Gemini to filter and select best images (with fallback)
    print(f"\n{'='*50}")
    print("Step 3: AI Image Selection")
    print("="*50)
    selected_indices = filter_relevant_images_with_gemini(prompt, all_image_results)
    
    # Determine number of images for collage
    num_images = min(len(selected_indices), random.choice([3, 4]))
    selected_indices = selected_indices[:num_images]
    
    print(f"✅ Selected {len(selected_indices)} most relevant images")
    
    # Step 4: Download selected images (with retry logic)
    print(f"\n{'='*50}")
    print("Step 4: Downloading Selected Images")
    print("="*50)
    images = []
    attributions = []
    
    # Try to download selected images first
    for idx in selected_indices:
        if len(images) >= num_images:
            break
            
        img_info = all_image_results[idx]
        print(f"📥 Downloading: {img_info['title'][:60]}...")
        
        img = api_handler.download_image(img_info['url'])
        if img:
            images.append(img)
            attributions.append({
                'photographer': img_info.get('source', 'Google Search'),
                'photographer_url': img_info['url'],
                'source': 'google'
            })
            print(f"   ✅ Success ({len(images)}/{num_images})")
        else:
            print(f"   ❌ Download failed, trying next image...")
    
    # If we don't have enough images, try downloading from ALL results
    if len(images) < 2:
        print(f"\n⚠️ Only got {len(images)} images from selected set. Trying all results...")
        
        for idx, img_info in enumerate(all_image_results):
            if len(images) >= 4:  # Get up to 4 images
                break
            
            # Skip already tried images
            if idx in selected_indices:
                continue
            
            print(f"📥 Trying image {idx}: {img_info['title'][:50]}...")
            img = api_handler.download_image(img_info['url'])
            
            if img:
                images.append(img)
                attributions.append({
                    'photographer': img_info.get('source', 'Google Search'),
                    'photographer_url': img_info['url'],
                    'source': 'google'
                })
                print(f"   ✅ Success! ({len(images)} total)")
    
    if len(images) < 2:
        raise Exception(f"❌ Failed to download enough images. Only downloaded {len(images)}. Need at least 2.\n"
                       f"💡 Tried {len(all_image_results)} different image URLs\n"
                       f"   - Most images may be protected/blocked by source websites\n"
                       f"   - Try running again later or check your internet connection")
    
    print(f"✅ Successfully downloaded {len(images)} images")
    
    # Step 5: Create collage
    print(f"\n{'='*50}")
    print("Step 5: Creating Collage")
    print("="*50)
    layout = select_optimal_layout(len(images))
    print(f"🎨 Using layout: {layout}")
    
    collage = create_collage_layout(images, layout, prompt)
    collage = add_attribution_watermark(collage, attributions)
    
    # Save with optimization
    collage.save(output_path, 'WEBP', quality=IMAGE_QUALITY, optimize=OPTIMIZE_IMAGE, method=6)
    
    file_size = os.path.getsize(output_path)
    print(f"✅ Collage saved: {output_path}")
    print(f"📊 File size: {file_size / 1024:.1f} KB")
    
    # Log image sources
    print(f"\n📸 Image sources:")
    for i, attr in enumerate(attributions, 1):
        print(f"   {i}. From {attr['source']} via Google Search")
    
    return True


def select_optimal_layout(num_images):
    """Select best layout based on number of images"""
    
    if num_images >= 4:
        # For 4+ images, randomly choose between grid layouts
        return random.choice(['grid_2x2', 'hero_with_strip'])
    elif num_images == 3:
        # For 3 images, use featured plus or horizontal strip
        return random.choice(['featured_plus', 'grid_1x3'])
    else:
        # For 2 images, split vertically
        return 'split_vertical'


def create_collage_layout(images, layout, title):
    """Create collage with specified layout and title overlay"""
    
    # Use 1920x1080 for 16:9 aspect ratio
    width = 1920
    height = 1080
    
    canvas = Image.new('RGB', (width, height), (245, 245, 245))  # Light gray background
    gap = 2  # Gap between images
    
    if layout == 'grid_2x2':
        # 2x2 grid layout (4 images)
        while len(images) < 4:
            images.append(images[0])
        
        img_w = (width - gap) // 2
        img_h = (height - gap) // 2
        
        positions = [
            (0, 0),
            (img_w + gap, 0),
            (0, img_h + gap),
            (img_w + gap, img_h + gap)
        ]
        
        for i, (x, y) in enumerate(positions[:4]):
            img = resize_and_crop(images[i], img_w, img_h)
            canvas.paste(img, (x, y))
    
    elif layout == 'grid_1x3':
        # Horizontal 3-image strip
        while len(images) < 3:
            images.append(images[0])
        
        img_w = (width - 2 * gap) // 3
        img_h = height
        
        for i in range(3):
            x = i * (img_w + gap)
            img = resize_and_crop(images[i], img_w, img_h)
            canvas.paste(img, (x, 0))
    
    elif layout == 'split_vertical':
        # 2 images side by side
        while len(images) < 2:
            images.append(images[0])
        
        img_w = (width - gap) // 2
        img_h = height
        
        img1 = resize_and_crop(images[0], img_w, img_h)
        canvas.paste(img1, (0, 0))
        
        img2 = resize_and_crop(images[1], img_w, img_h)
        canvas.paste(img2, (img_w + gap, 0))
    
    elif layout == 'featured_plus':
        # 1 large image + 2 smaller on side
        while len(images) < 2:
            images.append(images[0])
        
        main_w = int(width * 0.68)
        side_w = width - main_w - gap
        side_h = (height - gap) // 2
        
        # Main large image (left)
        main_img = resize_and_crop(images[0], main_w, height)
        canvas.paste(main_img, (0, 0))
        
        # Top right
        img2 = resize_and_crop(images[1], side_w, side_h)
        canvas.paste(img2, (main_w + gap, 0))
        
        # Bottom right (use third image or duplicate)
        img3_idx = 2 if len(images) > 2 else 1
        img3 = resize_and_crop(images[img3_idx], side_w, side_h)
        canvas.paste(img3, (main_w + gap, side_h + gap))
    
    elif layout == 'hero_with_strip':
        # Large hero image with strip of smaller images below
        while len(images) < 3:
            images.append(images[0])
        
        hero_h = int(height * 0.65)
        strip_h = height - hero_h - gap
        strip_w = (width - 2 * gap) // 3
        
        # Hero image (top)
        hero = resize_and_crop(images[0], width, hero_h)
        canvas.paste(hero, (0, 0))
        
        # Bottom strip (3 images)
        for i in range(3):
            x = i * (strip_w + gap)
            y = hero_h + gap
            img_idx = min(i + 1, len(images) - 1)
            img = resize_and_crop(images[img_idx], strip_w, strip_h)
            canvas.paste(img, (x, y))
    
    return canvas


def resize_and_crop(img, target_w, target_h):
    """Resize and center-crop image to exact dimensions"""
    
    # Calculate ratios
    img_ratio = img.width / img.height
    target_ratio = target_w / target_h
    
    # Resize to cover target area
    if img_ratio > target_ratio:
        # Image is wider - fit height
        new_h = target_h
        new_w = int(new_h * img_ratio)
    else:
        # Image is taller - fit width
        new_w = target_w
        new_h = int(new_w / img_ratio)
    
    img = img.resize((new_w, new_h), Image.Resampling.LANCZOS)
    
    # Center crop
    left = (new_w - target_w) // 2
    top = (new_h - target_h) // 2
    right = left + target_w
    bottom = top + target_h
    
    return img.crop((left, top, right, bottom))


def add_attribution_watermark(collage, attributions):
    """Add small attribution watermark in corner"""
    
    if not attributions:
        return collage
    
    draw = ImageDraw.Draw(collage, 'RGBA')
    
    text = "Images: Google Search"
    
    # Load small font
    try:
        font = ImageFont.truetype("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf", 14)
    except:
        font = ImageFont.load_default()
    
    # Calculate position (bottom right)
    bbox = draw.textbbox((0, 0), text, font=font)
    text_w = bbox[2] - bbox[0]
    text_h = bbox[3] - bbox[1]
    
    padding = 8
    x = collage.width - text_w - padding * 2 - 10
    y = collage.height - text_h - padding * 2 - 10
    
    # Draw semi-transparent background
    draw.rectangle(
        [
            (x - padding, y - padding),
            (x + text_w + padding, y + text_h + padding)
        ],
        fill=(0, 0, 0, 140)
    )
    
    # Draw white text
    draw.text((x, y), text, font=font, fill=(255, 255, 255, 220))
    
    return collage


def get_random_reference_image(reference_folder="assets/images"):
    """Kept for backward compatibility - not used in collage mode"""
    print("ℹ️ Reference images not used in collage mode")
    return None