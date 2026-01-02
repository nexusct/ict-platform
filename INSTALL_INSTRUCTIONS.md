# How to Install ICT Platform

## Super Simple Instructions (Anyone Can Follow!)

---

## Before You Start

You need to install 2 things on your computer first. Don't worry - it's easy!

### Step A: Install Node.js

1. Open your web browser (Chrome, Firefox, Safari, etc.)
2. Go to this website: **https://nodejs.org/**
3. Click the big green button that says **"LTS"** (this means "Long Term Support" - it's the safe choice!)
4. When the file downloads, double-click it to install
5. Click "Next" on everything until it's done
6. Restart your computer

### Step B: Install PHP (Optional - only needed for advanced features)

**Windows:**
1. Go to: **https://windows.php.net/download/**
2. Download the latest "Thread Safe" zip file
3. Extract it to `C:\php`
4. Add `C:\php` to your system PATH

**Mac:**
- PHP comes pre-installed! You're done!

**Linux:**
- Open Terminal and type: `sudo apt install php`

---

## Installing the ICT Platform

### For Mac or Linux Users

1. **Open Terminal**
   - On Mac: Press `Cmd + Space`, type "Terminal", press Enter
   - On Linux: Press `Ctrl + Alt + T`

2. **Go to the project folder**
   ```
   cd /path/to/ict-platform
   ```
   (Replace `/path/to/ict-platform` with where you saved the project)

3. **Run the install script**
   ```
   ./install.sh
   ```

4. **Wait for it to finish**
   - You'll see lots of text scrolling by - that's normal!
   - When it says "INSTALLATION COMPLETE" - you're done!

---

### For Windows Users

1. **Open the project folder**
   - Find where you saved the `ict-platform` folder
   - Double-click to open it

2. **Run the install script**
   - Find the file called `install.bat`
   - Double-click it

3. **Wait for it to finish**
   - A black window will open with text
   - When it says "INSTALLATION COMPLETE" - you're done!
   - Press any key to close the window

---

## What the Script Does

The install script automatically:

| Step | What Happens |
|------|--------------|
| 1 | Checks if Node.js and PHP are installed |
| 2 | Installs JavaScript packages for the WordPress plugin |
| 3 | Installs PHP packages (if Composer is installed) |
| 4 | Builds the WordPress plugin files |
| 5 | Installs packages for the mobile app |

---

## Manual Installation (If the Script Doesn't Work)

If the automatic script doesn't work, you can do it manually:

### WordPress Plugin

1. Open Terminal (Mac/Linux) or Command Prompt (Windows)

2. Go to the WordPress plugin folder:
   ```
   cd wp-ict-platform
   ```

3. Install the packages:
   ```
   npm install
   ```

4. Build the plugin:
   ```
   npm run build
   ```

### Mobile App

1. Go to the mobile app folder:
   ```
   cd ict-mobile-app
   ```

2. Install the packages:
   ```
   npm install
   ```

---

## Troubleshooting

### "npm is not recognized" or "command not found"

**What it means:** Node.js isn't installed properly.

**How to fix:**
1. Go to https://nodejs.org/
2. Download and install the LTS version
3. Restart your computer
4. Try again

---

### "Permission denied"

**What it means:** You don't have permission to run the script.

**How to fix (Mac/Linux):**
```
chmod +x install.sh
./install.sh
```

---

### "EACCES: permission denied" during npm install

**How to fix:**
- On Mac/Linux: Run with `sudo npm install`
- On Windows: Right-click Command Prompt and select "Run as Administrator"

---

### The script stops and shows red text

**What it means:** Something went wrong.

**How to fix:**
1. Read the error message
2. Usually it tells you what's missing
3. Install what's missing
4. Run the script again

---

## Quick Reference Card

| What You Want To Do | Command |
|---------------------|---------|
| Install everything | `./install.sh` (Mac/Linux) or double-click `install.bat` (Windows) |
| Install JS packages | `npm install` |
| Build for production | `npm run build` |
| Build for development | `npm run dev` |
| Run tests | `npm test` |
| Check for code errors | `npm run lint` |
| Start mobile app | `cd ict-mobile-app && npm start` |

---

## Need More Help?

- Check the `README.md` file for more details
- Look at `TROUBLESHOOTING_GUIDE.md` for common problems
- Make sure you have a good internet connection (packages download from the internet)

---

**That's it! You did it!**
