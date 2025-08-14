# Awesome Stripe Dashboard
This simple, yet powerful Stripe dashboard will allow you to set a MRR goal, and then connect to your Stripe
account and then data will be pulled from Stripe, your current current MRR, ARR, and how much is left to the goal
and how many are on the trial. This entire dashboard is created in PHP, one page and password protected. 

Simple, yet effective. 

Shoutout to weezly.com for sharing this awesome dashboard 🙏

## 📸 Screenshots
Stripe dashboard without trialing subscriptions enabled 
![App Screenshot](https://weezly.com/wp-content/uploads/2025/08/chrome_vnp7nlZ490.png)

Stripe dashboard with trialing subscriptions enabled 
![App Screenshot](https://weezly.com/wp-content/uploads/2025/08/chrome_LKZFxNJxJr.png)

Set your MRR goal in settings & disable enable trial subscriptions
![App Screenshot](https://weezly.com/wp-content/uploads/2025/08/chrome_uQ65sTM7wi.png)


## 📦 Installation

```bash
# Step 1 — Clone the project
git clone https://github.com/weezlydotcom/stripe-dashboard.git
```

```php
// Step 2 — Change the username and password (LINE 14 & 15)
$AUTH_USER = 'weezly'; // set your dashboard username
$AUTH_PASS = 'rocks';  // set your dashboard password
```

```php
// Step 4 — Set your currency (LINE 19)
$DASH_CURRENCY = 'sek'; // change this to the currency you want
```

```php
// Step 5 — Set your LIVE Stripe key (LINE 162)
$STRIPE_API_KEY = 'sk_live_'; // set your stripe key here
```

```text
Step 5 — ENJOY IT!
```


