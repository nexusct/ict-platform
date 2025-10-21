# ICT Platform - Simple Setup Guide
## For Non-Technical Users

---

## 📖 What Is This?

The **ICT Platform** is a complete business management system for electrical and ICT contractors. Think of it like having a super-smart assistant that:

- 🔄 Automatically moves information between QuoteWerks, your website, and Zoho
- 📱 Lets technicians track their time using their phones
- 📍 Knows where your technicians are during the day
- 📊 Shows you reports about your business
- 🔔 Reminds technicians to update what they're working on

---

## 🎯 What You Need Before Starting

### 1. A WordPress Website
- Like having a house for the software to live in
- Needs to be version 6.4 or newer
- Must have HTTPS (the lock icon in your browser)

### 2. Accounts You Already Use
- **QuoteWerks** - Where you create quotes for customers
- **Zoho** - Your customer management system
  - Zoho CRM (customers and deals)
  - Zoho FSM (field service)
  - Zoho Books (accounting)
  - Zoho People (employee time tracking)

### 3. Access to Your Website
- Username and password to log into WordPress
- Permission to install plugins (you're the admin)

---

## 📦 Step-by-Step Installation

### Part 1: Install the WordPress Plugin

**What you're doing:** Adding the ICT Platform software to your website

1. **Get the Files**
   - You should have a folder called `wp-ict-platform`
   - This has all the software inside

2. **Upload to WordPress**
   - Log into your WordPress website
   - Go to: Plugins → Add New → Upload Plugin
   - Choose the `wp-ict-platform` folder
   - Click "Install Now"
   - Click "Activate" when it's done

3. **Check It Worked**
   - Look at the left sidebar in WordPress
   - You should see "ICT Platform" in the menu
   - Click it - you should see a Settings page

✅ **Success!** The software is installed on your website!

---

### Part 2: Connect to QuoteWerks

**What you're doing:** Teaching the software where to find your quotes

1. **Find Your QuoteWerks Information**
   - You need 3 things:
     - QuoteWerks website address (like: `https://quotewerks.yourcompany.com`)
     - Username
     - Password or API key

2. **Enter It in WordPress**
   - Go to: ICT Platform → Settings → QuoteWerks tab
   - Type in your website address
   - Type in your username
   - Type in your password/API key
   - Click "Save Changes"

3. **Test It**
   - Click the "Test Connection" button
   - You should see: "✅ Connected successfully!"
   - If not, double-check what you typed

✅ **Success!** WordPress can now talk to QuoteWerks!

---

### Part 3: Connect to Zoho

**What you're doing:** Teaching the software where to find your customer information

**You'll do this 5 times - once for each Zoho product:**
- Zoho CRM
- Zoho FSM
- Zoho Books
- Zoho People
- Zoho Desk

**For Each Zoho Product:**

1. **Get Your Zoho "Keys"**
   - Go to: https://api-console.zoho.com/
   - Log in with your Zoho account
   - Click "Add Client" → "Server-based Applications"
   - Give it a name like "ICT Platform CRM"
   - For "Redirect URL" enter: `https://yourwebsite.com/wp-admin/`
   - Click "Create"
   - **Save these two things:**
     - Client ID (a long number/letter code)
     - Client Secret (another long code)

2. **Enter Them in WordPress**
   - Go to: ICT Platform → Settings → [Zoho Product] tab
   - Paste in your Client ID
   - Paste in your Client Secret
   - Click "Save Changes"

3. **Give Permission**
   - Click the "Authorize" button
   - A Zoho page will open
   - Click "Accept" to let the software access your Zoho
   - You'll be sent back to WordPress
   - You should see: "✅ Connected!"

4. **Repeat for All 5 Zoho Products**

✅ **Success!** WordPress can now talk to all your Zoho products!

---

### Part 4: Install the Mobile App

**What you're doing:** Giving your technicians an app for their phones

#### For iPhone Users:

1. **Build the App** (your tech person does this)
   - They'll create an iPhone app file
   - They'll upload it to the Apple App Store
   - Or use Apple TestFlight for testing

2. **Technicians Install It**
   - Search "Your Company ICT Platform" in App Store
   - Tap "Get" and install
   - Open the app
   - Enter their username and password
   - They're ready to go!

#### For Android Users:

1. **Build the App** (your tech person does this)
   - They'll create an Android app file
   - They'll upload it to Google Play Store
   - Or share the APK file directly

2. **Technicians Install It**
   - Search "Your Company ICT Platform" in Play Store
   - Tap "Install"
   - Open the app
   - Enter their username and password
   - They're ready to go!

✅ **Success!** Your technicians have the app!

---

## 🔄 How It Works: The Magic Explained

### Scenario: You Create a Quote

**What Happens Automatically:**

```
1. YOU → Create quote in QuoteWerks
   "Install 10 security cameras for ABC Company - $5,000"

2. QuoteWerks → Tells WordPress
   "Hey! New quote just created!"

3. WordPress → Creates a project
   Project Name: ABC Company - Security Cameras
   Budget: $5,000
   Status: Planning

4. WordPress → Tells Zoho CRM
   "Create a Deal for this project"

5. Zoho CRM → Creates Deal
   Deal Name: ABC Company - Security Cameras
   Amount: $5,000
   Stage: Qualification

6. WordPress → Tells Zoho FSM
   "Create a Work Order for this project"

7. Zoho FSM → Creates Work Order
   Subject: ABC Company - Security Cameras
   Status: Scheduled

All in less than 1 minute!
```

**Before:** You had to manually copy this information 3 times
**Now:** It happens automatically!

---

### Scenario: Technician Goes to Work

**What Happens:**

```
1. TECHNICIAN → Opens mobile app
   8:00 AM - Arrives at ABC Company

2. Taps "Clock In" button
   App captures GPS location: "123 Main Street"

3. Starts working...
   App tracks location every 5 minutes in background

4. Drives to different job site
   10 miles away - 10:30 AM

5. App notices: "You're far from your last location!"
   Shows reminder: "Are you working on a different task?"

6. Technician updates task
   Now working on: "XYZ Company - Network Install"

7. Taps "Clock Out" at end of day
   5:00 PM - Total time: 9 hours

8. Time entry automatically syncs to:
   - WordPress (project manager sees it)
   - Zoho People (payroll sees it)
```

**Before:** Technician wrote times on paper, you entered manually
**Now:** Automatic with GPS proof!

---

## 📊 What You Can See

### In WordPress Admin:

1. **Dashboard**
   - How many active projects
   - Who's clocked in right now
   - Budget vs actual costs
   - Upcoming deadlines

2. **Projects**
   - All projects from QuoteWerks
   - Linked to Zoho deals
   - Track progress and time

3. **Time Tracking**
   - Approve technician time entries
   - See GPS locations where they worked
   - Export for payroll

4. **Reports**
   - Project profitability
   - Technician productivity
   - Inventory levels
   - Budget tracking

### On Mobile App (Technicians See):

1. **Home Screen**
   - Big "Clock In" / "Clock Out" button
   - Current task timer
   - Today's schedule

2. **Projects**
   - Their assigned projects
   - Task lists
   - Customer information

3. **Time Tracking**
   - Current time entry
   - History of past entries
   - Weekly totals

4. **Expenses**
   - Submit expense (take picture of receipt)
   - Track reimbursements

---

## 🎓 Teaching Your Team

### For Office Staff:

**What They Need to Know:**
- Projects automatically come from QuoteWerks
- Approve time entries in WordPress
- Check sync status (is everything connected?)
- Run reports when needed

**Training Time:** 30 minutes

### For Technicians:

**What They Need to Know:**
- How to clock in/out
- How to switch tasks
- How to submit expenses
- GPS is tracking (for their protection and yours!)

**Training Time:** 15 minutes

### For Project Managers:

**What They Need to Know:**
- How projects flow through the system
- Approving time and expenses
- Running reports
- What to do if something doesn't sync

**Training Time:** 1 hour

---

## 🚨 Simple Troubleshooting

### "QuoteWerks quotes aren't showing up!"

**Check:**
1. Is the website still connected? (Settings → QuoteWerks → Test Connection)
2. Did you enable the webhook in QuoteWerks?
3. Check the sync log (Settings → Sync Log)

**Fix:**
- Re-enter your QuoteWerks password
- Click "Test Connection" again

---

### "Zoho isn't updating!"

**Check:**
1. Go to Settings → Zoho → [Product]
2. Click "Test Connection"
3. Does it say "Connected"?

**Fix:**
- Click "Disconnect" then "Authorize" again
- This refreshes the connection

---

### "Mobile app won't log in!"

**Check:**
1. Is the username/password correct?
2. Is the internet working?
3. Is the website online?

**Fix:**
- Reset the password in WordPress
- Try logging in on the website first
- Make sure they're entering the full username

---

### "GPS isn't working!"

**Check:**
1. Did they allow location permissions?
2. Is GPS turned on?
3. Are they indoors? (GPS works poorly indoors)

**Fix:**
- iPhone: Settings → App → Location → "Always Allow"
- Android: Settings → Apps → Permissions → Location → "Allow all the time"

---

## ✅ Daily Checklist (5 Minutes)

### Every Morning:

1. **Check the Health Status**
   - Go to: ICT Platform → Dashboard
   - Look for green checkmarks ✅
   - All services should say "Connected"

2. **Check Sync Queue**
   - Should say: "0 pending" or less than 10
   - If more than 50, call support

3. **Review Yesterday's Activity**
   - How many quotes synced?
   - How many time entries?
   - Any errors? (red marks)

**Takes:** 5 minutes
**Do it:** Every business day
**Why:** Catches problems early!

---

## 🎉 You're Done!

### What You've Accomplished:

✅ Installed the ICT Platform on your website
✅ Connected QuoteWerks
✅ Connected all 5 Zoho products
✅ Set up mobile apps for your technicians
✅ Understand how the system works
✅ Know basic troubleshooting

### What Happens Now:

- Quotes automatically become projects
- Projects automatically sync to Zoho
- Technicians track time with their phones
- GPS proves where they worked
- You see everything in real-time
- Reports show how your business is doing

### Time Saved:

**Before:** 2-3 hours per day entering data manually
**After:** 5 minutes checking that it worked

**That's 10-15 hours per week back in your schedule!**

---

## 📞 Need Help?

### First Steps:
1. Check this guide
2. Check the Troubleshooting Guide
3. Look at the health check dashboard

### Still Stuck?
- Email: support@yourcompany.com
- Include:
  - What you were trying to do
  - What happened instead
  - Screenshot if possible

### Emergency (System Down)?
- Follow the Rollback Guide
- Call technical support immediately

---

## 🌟 Pro Tips

1. **Train in stages** - Don't try to learn everything day one
2. **Start with one technician** - Perfect the process before rolling out to everyone
3. **Check daily** - The 5-minute health check prevents big problems
4. **Celebrate wins** - When you save time, acknowledge it!
5. **Give feedback** - If something is confusing, ask for improvement

---

**Remember:** This system is here to make your life EASIER, not harder!

If you're spending more than 10 minutes a day managing it, something's wrong - ask for help!

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)

**This guide was written to be simple. If anything is confusing, let us know!**
