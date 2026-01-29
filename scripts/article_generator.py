"""Generate blog articles using Gemini AI"""
from google import genai
from config import TEXT_MODEL, GEMINI_API_KEY
import re

client = genai.Client(api_key=GEMINI_API_KEY)

author_name = "AutoBlogging Bot"
def generate_article(title, focus_kw, permalink, semantic_kw, affiliate_links, hook_kw, search_kw):
    """Generate SEO-optimized blog article"""
    prompt = f"""
write an SEO-optimised blog on the title {title}. using the Focus keyword {focus_kw},  using LSI Keywords {semantic_kw}, also use {search_kw} for  extra search intent
use the following
Rules:
- Simple Language, a 10 year old can understand (follow title language).
- Don't write more than 3 sentences per paragraph, changes paragraph after 3 sentences
- Use "you" to address the reader
- Hook: {hook_kw}
- link other websites for external linking where relevant
- don't use H1, only Use H2 and H3, h4, h5, h6 headings 
- add affiliate links where relevant: {affiliate_links}
- Use lists, tables, snippets, and other data formats
- Write more than 2000 words
- Add FAQ sections
- Write in Jekyll format article filename extension .md only
- Naturally include focused & semantic keywords
- do not add any front matter or meta data
"""
    # - also include related  affiliate links where it fits best: {affiliate_links},
    
    print("🤖 Generating article with Gemini...")
    response = client.models.generate_content(
        model=TEXT_MODEL,
        contents=prompt
    )
    
    # Remove any front matter that AI might have added
    content = remove_front_matter(response.text)
    
    # Add custom front matter
    article = create_custom_front_matter(title, focus_kw, permalink) + "\n\n" + content
    
    return article

def remove_front_matter(content):
    """Remove any existing front matter from AI-generated content"""
    # Remove front matter between --- delimiters
    content = re.sub(r'^---\s*\n.*?\n---\s*\n', '', content, flags=re.DOTALL)
    
    # Remove any stray YAML-like lines at the beginning
    lines = content.split('\n')
    clean_lines = []
    skip_yaml = True
    
    for line in lines:
        # Stop skipping once we hit actual content (heading or paragraph)
        if line.strip().startswith('#') or (line.strip() and not ':' in line):
            skip_yaml = False
        
        if not skip_yaml:
            clean_lines.append(line)
        elif skip_yaml and line.strip() and ':' not in line:
            # This is actual content, not YAML
            skip_yaml = False
            clean_lines.append(line)
    
    return '\n'.join(clean_lines).strip()


def create_custom_front_matter(title, focus_kw, permalink):
    """Create properly formatted Jekyll front matter"""
    # Escape quotes in title
    escaped_title = title.replace('"', '\\"')
    
    # Generate description (you can make this dynamic)
    description = generate_description(title, focus_kw)
    
    # Create front matter - NO LEADING SPACES!
    front_matter = f"""---
layout: post
title: "{escaped_title}"
description: "{description}"
keywords: "{focus_kw}"
author: "{author_name}"
image: assets/images/featured_{permalink}.webp
---"""
       
    return front_matter

def generate_description(title, focus_kw):
    """Generate SEO-optimized meta description (160 characters)"""
    prompt = f"""
Generate a compelling meta description for this blog post.

Title: {title}
Focus Keyword: {focus_kw}

Requirements:
- EXACTLY 150-160 characters (this is critical)
- Include the focus keyword naturally
- Action-oriented and engaging
- Make readers want to click
- No quotes or special characters
- Complete sentence

Return ONLY the description text, nothing else.
"""
    
    print("📝 Generating meta description...")
    response = client.models.generate_content(
        model=TEXT_MODEL,
        contents=prompt
    )
    
    description = response.text.strip()
    
    # Ensure it's under 160 characters
    if len(description) > 160:
        description = description[:157] + "..."
    
    print(f"✅ Description generated: {description} ({len(description)} chars)")
    
    return description

def generate_image_prompt(title):
    """Generate image prompt for Freepik AI"""
    
    # Remove celebrity names from title for image generation
    title_cleaned = title.replace("Cristiano Ronaldo", "professional athlete")
    title_cleaned = title_cleaned.replace("Ronaldo", "elite footballer")
    title_cleaned = title_cleaned.replace("CR7", "top athlete")
    
    prompt = f"""
create a search term to get a high-quality image  related to the blog post with the following from unsplash and pixel
Title: {title_cleaned}

Requirements:
- Professional male athlete in peak physical condition
- Athletic build, focused and determined expression
- High-quality, realistic sports/training scene
- Modern fitness environment or training facility
- NO text or words in the image


Return ONLY the image prompt, nothing else.
"""
    
    print("🎨 Generating image prompt...")
    response = client.models.generate_content(
        model=TEXT_MODEL,
        contents=prompt
    )
    
    image_prompt = response.text.strip()
    
    # Additional safety check - remove any celebrity names from generated prompt
    image_prompt = image_prompt.replace("Cristiano Ronaldo", "professional athlete")
    image_prompt = image_prompt.replace("Ronaldo", "elite footballer")
    image_prompt = image_prompt.replace("CR7", "top athlete")
    
    print(f"✅ Image prompt: {image_prompt[:100]}...")
    
    return image_prompt
