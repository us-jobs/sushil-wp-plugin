"""Main script to generate blog posts automatically with GSC indexing"""
import os
import random
import time
import datetime

# Import all modules
from config import *
from keywords_handler import get_keyword_row, parse_keyword_row, remove_keyword_from_file, get_keywords_count
from article_generator import generate_article  # Removed unused generate_image_prompt
from image_generator import generate_article_image
from webpushr_notifier import send_blog_post_notification, get_subscriber_count


def main():
    print("=" * 60)
    print("🚀 Starting Blog Post Generator with Auto-Indexing")
    print("=" * 60)
    
    # Verify environment variables
    if not GEMINI_API_KEY:
        print("❌ GEMINI_API_KEY not found")
        return
    print("✅ GEMINI_API_KEY found")
    
    # Check for Google Custom Search API keys (for image generation)
    if not GOOGLE_SEARCH_API_KEY or not GOOGLE_SEARCH_ENGINE_ID:
        print("❌ Google Custom Search API credentials not found")
        print("   Required: GOOGLE_SEARCH_API_KEY and GOOGLE_SEARCH_ENGINE_ID")
        print("   Image generation will fail without these keys")
        return
    print("✅ GOOGLE_SEARCH_API_KEY found")
    print("✅ GOOGLE_SEARCH_ENGINE_ID found")
    
    # Check for Instagram credentials (optional)
    instagram_enabled = bool(os.environ.get('INSTAGRAM_USERNAME') and os.environ.get('INSTAGRAM_PASSWORD'))
    if instagram_enabled:
        print("✅ Instagram credentials found - auto-posting enabled")
    else:
        print("ℹ️ Instagram credentials not found - skipping Instagram posts")
    
    # Show keywords status
    keywords_count = get_keywords_count()
    print(f"\n📊 Posts to generate this run: {POSTS_PER_RUN}")
    print(f"📋 Keywords available: {keywords_count}")
    
    posts_generated = 0
    urls_to_index = []  # Collect URLs for batch indexing
    
    for post_num in range(1, POSTS_PER_RUN + 1):
        print(f"\n{'=' * 60}")
        print(f"📝 Processing Post {post_num}/{POSTS_PER_RUN}")
        print("=" * 60)
        
        # Get next keyword
        row = get_keyword_row()
        if not row:
            print(f"❌ No more keywords left")
            break
        
        print(f"\n📋 Keyword: {row[:80]}...")
        
        # Parse keyword with new format
        keyword_data = parse_keyword_row(row)
        if not keyword_data:
            print(f"❌ Invalid keyword format")
            remove_keyword_from_file()  # Remove invalid keyword
            continue
        
        title = keyword_data['title']
        focus_kw = keyword_data['focus_kw']
        permalink = keyword_data['permalink']
        semantic_kw = keyword_data['semantic_kw']
        affiliate_links = keyword_data['affiliate_links']
        hook_kw = keyword_data.get('hook_kw', '')
        search_kw = keyword_data.get('search_kw', '')
        
        print(f"✅ Parsed: {title[:60]}...")
        
        # Generate file paths
        today = datetime.date.today().isoformat()
        post_path = f"{POSTS_DIR}/{today}-{permalink}.md"
        image_file = f"{IMAGES_DIR}/featured_{permalink}.webp"
        
        # Check if post already exists
        if os.path.exists(post_path):
            print(f"\n⚠️  Post already exists: {post_path}")
            remove_keyword_from_file()  # Remove duplicate
            continue
        
        # Generate content
        try:
            # Step 1: Generate article
            print(f"\n{'=' * 60}")
            print("Step 1: Generating Article")
            print("=" * 60)
            article = generate_article(title, focus_kw, permalink, semantic_kw, affiliate_links, hook_kw, search_kw)
            print(f"✅ Article generated ({len(article)} characters)")
            
            
            # Step 2: Create featured image (using Google Custom Search API + Gemini AI)
            print(f"\n{'=' * 60}")
            print("Step 2: Creating AI-Powered Collage with Relevant Images")
            print("=" * 60)
            
            try:
                # Gemini will dynamically generate search queries based on article title
                generate_article_image(
                    title,  # Article title - works with ANY topic (Messi, Ronaldo, products, etc.)
                    image_file
                )
                print(f"✅ Featured image collage created successfully")
                
                # Add image to git
                if os.path.exists(image_file):
                    os.system(f"git add {image_file}")
                    print(f"✅ Image added to git: {image_file}")
                
            except Exception as img_error:
                print(f"❌ Image creation failed: {img_error}")
                print(f"⚠️ Skipping this post - will retry next run")
                import traceback
                traceback.print_exc()
                # Don't remove keyword so it can be retried
                continue
            
            # Step 3: Save post
            print(f"\n{'=' * 60}")
            print("Step 3: Saving Post")
            print("=" * 60)
            with open(post_path, "w", encoding="utf-8") as f:
                f.write(article)
            print(f"✅ Post saved: {post_path}")
            
            post_url = f"{SITE_DOMAIN}/{permalink}"
            
            print(f"\n{'=' * 60}")
            print(f"✅ SUCCESS! Post {post_num} Generated")
            print("=" * 60)
            print(f"📰 Title: {title}")
            print(f"🌐 URL: {post_url}")
            
            posts_generated += 1
            
            # Add URL to indexing queue
            urls_to_index.append(post_url)
        
                
            # Step 4: Send Push Notification
            try:
                send_blog_post_notification(title, permalink, focus_kw)
                print(f"✅ Push notification sent")
            except Exception as e:
                print(f"⚠️ Push notification failed (non-critical): {e}")
            
            # Step 5: Remove keyword after success
            print(f"\n{'=' * 60}")
            print("Step 5: Removing Keyword from File")
            print("=" * 60)
            remove_keyword_from_file()
            print(f"✅ Keyword removed - post complete")
            
        except Exception as e:
            print(f"\n{'=' * 60}")
            print(f"❌ FAILED: {e}")
            print("=" * 60)
            print(f"⚠️ Keyword NOT removed - will retry next run")
            import traceback
            traceback.print_exc()
            continue
    
    # Final summary
    print(f"\n{'=' * 60}")
    print("🎉 WORKFLOW COMPLETE")
    print("=" * 60)
    print(f"✅ Posts generated: {posts_generated}")
    print(f"📊 Keywords remaining: {get_keywords_count()}")
    
    if urls_to_index:
        print(f"🔍 URLs submitted for indexing: {len(urls_to_index)}")
    
    if posts_generated == 0:
        print(f"\n⚠️ No posts were generated this run")
        print(f"💡 Check the logs above for errors")


if __name__ == "__main__":
    main()