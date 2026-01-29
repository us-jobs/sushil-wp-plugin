"""Send push notifications via Webpushr"""
import os
import requests
from config import SITE_DOMAIN
from article_generator import generate_description

# Webpushr API credentials from environment
WEBPUSHR_API_KEY = os.environ.get("WEBPUSHR_API_KEY")
WEBPUSHR_AUTH_TOKEN = os.environ.get("WEBPUSHR_AUTH_TOKEN")


def send_webpushr_notification(title, message, target_url, image_url=None):
    """
    Send push notification via Webpushr
    
    Args:
        title: Notification title
        message: Notification message
        target_url: URL to open when clicked
        image_url: Optional large image URL
    
    Returns:
        bool: Success status
    """
    
    if not WEBPUSHR_API_KEY:
        print("⚠️ WEBPUSHR_API_KEY not found - skipping notification")
        return False
    
    if not WEBPUSHR_AUTH_TOKEN:
        print("⚠️ WEBPUSHR_AUTH_TOKEN not found - skipping notification")
        return False
    
    try:
        print(f"🔔 Sending Webpushr notification...")
        
        # Webpushr API endpoint
        api_url = "https://api.webpushr.com/v1/notification/send/all"
        
        # Prepare headers
        headers = {
            "webpushrKey": WEBPUSHR_API_KEY,
            "webpushrAuthToken": WEBPUSHR_AUTH_TOKEN,
            "Content-Type": "application/json"
        }
        
        # Prepare notification data
        payload = {
            "title": title,
            "message": message,
            "target_url": target_url,
            "icon": f"{SITE_DOMAIN}/assets/images/site-logo.webp",  # Your site logo
            "auto_hide": 1  # Auto hide after shown
        }
        
        # Add large image if provided
        if image_url:
            payload["image"] = image_url
        
        # Send notification
        response = requests.post(api_url, headers=headers, json=payload, timeout=30)
        
        if response.status_code == 200:
            result = response.json()
            print(f"✅ Notification sent successfully!")
            print(f"📊 Queue ID: {result.get('qid', 'N/A')}")
            return True
        else:
            print(f"❌ Failed to send notification: {response.status_code}")
            print(f"Response: {response.text}")
            return False
        
    except Exception as e:
        print(f"❌ Error sending Webpushr notification: {e}")
        return False


def send_blog_post_notification(title, permalink, focus_kw):
    """
    Send notification for new blog post
    
    Args:
        title: Blog post title
        permalink: Post permalink
        focus_kw: Focus keyword for categorization
    """
    
    # Construct full URL
    post_url = f"{SITE_DOMAIN}/{permalink}"
    image_url = f"{SITE_DOMAIN}/assets/images/featured_{permalink.strip('/').split('/')[-1]}.webp"
    description = generate_description(title, focus_kw)
    # Create notification message
    notification_title = f"{title[:80]}"
    notification_message = f"{description}"
    
    # Send notification
    return send_webpushr_notification(
        title=notification_title,
        message=notification_message,
        target_url=post_url,
        image_url=image_url
    )


def send_segmented_notification(title, message, target_url, segment_id=None):
    """
    Send notification to specific segment
    
    Args:
        title: Notification title
        message: Notification message
        target_url: URL to open when clicked
        segment_id: Webpushr segment ID (optional)
    
    Returns:
        bool: Success status
    """
    
    if not WEBPUSHR_API_KEY or not WEBPUSHR_AUTH_TOKEN:
        print("⚠️ Webpushr credentials not found")
        return False
    
    try:
        # Different endpoint for segmented notifications
        if segment_id:
            api_url = f"https://api.webpushr.com/v1/notification/send/sid/{segment_id}"
        else:
            api_url = "https://api.webpushr.com/v1/notification/send/all"
        
        headers = {
            "webpushrKey": WEBPUSHR_API_KEY,
            "webpushrAuthToken": WEBPUSHR_AUTH_TOKEN,
            "Content-Type": "application/json"
        }
        
        payload = {
            "title": title,
            "message": message,
            "target_url": target_url,
            "icon": f"{SITE_DOMAIN}/assets/images/site-logo.webp"
        }
        
        response = requests.post(api_url, headers=headers, json=payload, timeout=30)
        
        if response.status_code == 200:
            print(f"✅ Segmented notification sent!")
            return True
        else:
            print(f"❌ Failed: {response.status_code} - {response.text}")
            return False
            
    except Exception as e:
        print(f"❌ Error: {e}")
        return False


def send_action_button_notification(title, message, target_url, button_title="Read More", button_url=None):
    """
    Send notification with action buttons
    
    Args:
        title: Notification title
        message: Notification message
        target_url: Default URL to open
        button_title: Action button text
        button_url: URL for action button (optional)
    """
    
    if not WEBPUSHR_API_KEY or not WEBPUSHR_AUTH_TOKEN:
        print("⚠️ Webpushr credentials not found")
        return False
    
    try:
        api_url = "https://api.webpushr.com/v1/notification/send/all"
        
        headers = {
            "webpushrKey": WEBPUSHR_API_KEY,
            "webpushrAuthToken": WEBPUSHR_AUTH_TOKEN,
            "Content-Type": "application/json"
        }
        
        payload = {
            "title": title,
            "message": message,
            "target_url": target_url,
            "icon": f"{SITE_DOMAIN}/assets/images/site-logo.webp",
            "action_buttons": [
                {
                    "title": button_title,
                    "url": button_url or target_url,
                    "icon": ""
                }
            ]
        }
        
        response = requests.post(api_url, headers=headers, json=payload, timeout=30)
        
        if response.status_code == 200:
            print(f"✅ Action button notification sent!")
            return True
        else:
            print(f"❌ Failed: {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ Error: {e}")
        return False


def get_subscriber_count():
    """Get total subscriber count from Webpushr"""
    
    if not WEBPUSHR_API_KEY or not WEBPUSHR_AUTH_TOKEN:
        return None
    
    try:
        api_url = "https://api.webpushr.com/v1/subscribers/count"
        
        headers = {
            "webpushrKey": WEBPUSHR_API_KEY,
            "webpushrAuthToken": WEBPUSHR_AUTH_TOKEN
        }
        
        response = requests.get(api_url, headers=headers, timeout=30)
        
        if response.status_code == 200:
            data = response.json()
            count = data.get("count", 0)
            print(f"📊 Total subscribers: {count}")
            return count
        else:
            return None
            
    except Exception as e:
        print(f"❌ Error getting subscriber count: {e}")
        return None