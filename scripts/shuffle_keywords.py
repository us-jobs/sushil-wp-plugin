import random
import os
import sys

def shuffle_keywords_file():
    """
    Randomly shuffle all lines in keywords.txt
    Works from any directory - automatically finds keywords.txt
    """
    # Try multiple locations for keywords.txt
    possible_paths = [
        'keywords.txt',  # Current directory
        '../keywords.txt',  # Parent directory
        '../../keywords.txt',  # Grandparent directory
        os.path.join(os.getcwd(), 'keywords.txt'),  # Absolute current dir
    ]
    
    # If running from scripts folder, try parent
    script_dir = os.path.dirname(os.path.abspath(__file__))
    possible_paths.append(os.path.join(os.path.dirname(script_dir), 'keywords.txt'))
    
    # Find the file
    keywords_file = None
    for path in possible_paths:
        if os.path.exists(path):
            keywords_file = path
            break
    
    if not keywords_file:
        print(f"❌ Error: Could not find keywords.txt")
        print(f"📁 Current directory: {os.getcwd()}")
        print(f"📁 Script location: {script_dir}")
        print(f"\n💡 Please run from repository root:")
        print(f"   cd ecommercemart.github.io")
        print(f"   python scripts/shuffle_keywords.py")
        print(f"\n   Or place this script in the same folder as keywords.txt")
        return
    
    print(f"📖 Found keywords.txt at: {keywords_file}")
    print(f"📖 Reading file...")
    
    # Read all lines from the file
    try:
        with open(keywords_file, 'r', encoding='utf-8') as f:
            lines = [line.strip() for line in f if line.strip()]
    except Exception as e:
        print(f"❌ Error reading file: {e}")
        return
    
    if not lines:
        print(f"❌ Error: keywords.txt is empty!")
        return
    
    print(f"📊 Found {len(lines)} keywords")
    
    # Shuffle the lines randomly
    print(f"🔀 Shuffling keywords randomly...")
    random.shuffle(lines)
    
    # Write back to file
    print(f"💾 Writing shuffled keywords back to {keywords_file}...")
    try:
        with open(keywords_file, 'w', encoding='utf-8') as f:
            for line in lines:
                f.write(line + '\n')
    except Exception as e:
        print(f"❌ Error writing file: {e}")
        return
    
    print(f"✅ Done! All {len(lines)} keywords have been randomly shuffled.")
    print(f"\n📋 First 10 keywords after shuffle:")
    for i, line in enumerate(lines[:10], 1):
        # Show just the title (before first |)
        parts = line.split('|')
        if parts:
            title = parts[0].strip()
            # Truncate long titles
            if len(title) > 70:
                title = title[:67] + "..."
            print(f"   {i}. {title}")

if __name__ == "__main__":
    print("=" * 70)
    print("🔀 Keyword Shuffler")
    print("=" * 70)
    shuffle_keywords_file()
    print("=" * 70)