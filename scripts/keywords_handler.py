"""Handle reading and managing keywords.txt"""
import os
from config import KEYWORDS_FILE


def get_keyword_row():
    """Read first line from keywords.txt WITHOUT removing it"""
    if not os.path.exists(KEYWORDS_FILE):
        print(f"❌ {KEYWORDS_FILE} not found")
        return None
    
    try:
        with open(KEYWORDS_FILE, "r", encoding="utf-8") as f:
            lines = [l.strip() for l in f if l.strip()]
        
        if not lines:
            print(f"📋 {KEYWORDS_FILE} is empty")
            return None
        
        # Return first line WITHOUT removing it
        return lines[0]
        
    except Exception as e:
        print(f"❌ Error reading keywords.txt: {e}")
        return None


def parse_keyword_row(row):
    """
    Parse keyword row with new format:
    title | focus_kw | permalink | semantic_kw | affiliate_links | hook_kw | search_kw
    
    Returns:
        dict with all fields or None if invalid
    """
    try:
        parts = [x.strip() for x in row.split("|")]
        
        if len(parts) == 4:
            # Old format (backward compatible)
            return {
                'title': parts[0],
                'focus_kw': parts[1],
                'permalink': parts[2],
                'semantic_kw': parts[3],
                'affiliate_links': '',
                'hook_kw': '',
                'search_kw': ''
            }
        elif len(parts) == 5:
            # Old format (backward compatible)
            return {
                'title': parts[0],
                'focus_kw': parts[1],
                'permalink': parts[2],
                'semantic_kw': parts[3],
                'affiliate_links': parts[4],
                'hook_kw': '',
                'search_kw': ''
            }
        elif len(parts) == 7:
            # New format with social media
            return {
                'title': parts[0],
                'focus_kw': parts[1],
                'permalink': parts[2],
                'semantic_kw': parts[3],
                'affiliate_links': parts[4],
                'hook_kw': parts[5],
                'search_kw': parts[6]
            }
        else:
            print(f"❌ Invalid format. Expected 4 or 7 fields, got {len(parts)}")
            return None
            
    except Exception as e:
        print(f"❌ Error parsing keyword: {e}")
        return None


def remove_keyword_from_file():
    """Remove the first line from keywords.txt after successful generation"""
    if not os.path.exists(KEYWORDS_FILE):
        print(f"❌ {KEYWORDS_FILE} not found")
        return False
    
    try:
        with open(KEYWORDS_FILE, "r", encoding="utf-8") as f:
            lines = [l.strip() for l in f if l.strip()]
        
        if not lines:
            return False
        
        # Remove first line
        lines.pop(0)
        
        # Write remaining lines back
        with open(KEYWORDS_FILE, "w", encoding="utf-8") as f:
            for line in lines:
                f.write(line + "\n")
        
        print(f"✅ Removed keyword from file")
        print(f"📊 Keywords remaining: {len(lines)}")
        return True
        
    except Exception as e:
        print(f"❌ Error removing keyword: {e}")
        return False


def get_keywords_count():
    """Get the number of keywords remaining"""
    if not os.path.exists(KEYWORDS_FILE):
        return 0
    
    try:
        with open(KEYWORDS_FILE, "r", encoding="utf-8") as f:
            lines = [l.strip() for l in f if l.strip()]
        return len(lines)
    except:
        return 0