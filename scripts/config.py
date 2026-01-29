"""Configuration settings for the blog post generator"""
import os

# File paths
KEYWORDS_FILE = "keywords.txt"
POSTS_DIR = "_posts"
IMAGES_DIR = "assets/images"

# Site settings
SITE_DOMAIN = "https://your-account.github.io"

# AI Models
TEXT_MODEL = "gemini-2.5-flash"
FREEPIK_ENDPOINT = "https://api.freepik.com/v1/ai/text-to-image/flux-dev"

# Generation settings
POSTS_PER_RUN = 1  # How many posts to generate per run

# Image settings
IMAGE_QUALITY = 80  # 1-100 (80 = good balance)
IMAGE_MAX_WIDTH = 1920
IMAGE_MAX_HEIGHT = 1080
OPTIMIZE_IMAGE = True


# API Keys (from environment)
GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY")

# Google Custom Search API (for image generation)
GOOGLE_SEARCH_API_KEY = os.environ.get("GOOGLE_SEARCH_API_KEY")
GOOGLE_SEARCH_ENGINE_ID = os.environ.get("GOOGLE_SEARCH_ENGINE_ID")

# Create directories
os.makedirs(POSTS_DIR, exist_ok=True)
os.makedirs(IMAGES_DIR, exist_ok=True)