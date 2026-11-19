# Budget Choice Bd — Complete Laravel 11 + MySQL Project

এই প্রোজেক্টটি সম্পূর্ণ Laravel 11 আর্কিটেকচারে তৈরি করা হয়েছে (Firebase ছাড়াই)। আপনার সব অর্ডার, প্রোডাক্ট, কাস্টমার, সাপ্লায়ার, খরচ, অ্যাকাউন্ট, পার্সেল ও সেটিংস সরাসরি আপনার নিজের MySQL ডাটাবেসে সেভ ও রিয়েল-টাইম সিঙ্ক হবে।

---

## ১. সার্ভার রিকোয়ারমেন্ট
- PHP **8.2** বা তার উপরে (cPanel MultiPHP Manager-এ সেট করুন)
- MySQL **5.7+** বা **MariaDB 10.3+**
- Composer (লোকাল পিসিতে অথবা cPanel Terminal-এ `vendor/` ইনস্টল করার জন্য)

---

## ২. cPanel / সার্ভার ইনস্টলেশন গাইড

### ধাপ ১: ফাইল আপলোড ও Extract
1. cPanel **File Manager** → আপনার ডোমেইনের ফোল্ডারে (যেমন `public_html/` বা সাবডোমেইন ফোল্ডার) এই `budgetchoice-laravel11-full-project.zip` ফাইলটি আপলোড করে **Extract** করুন।

### ধাপ ২: Composer Dependencies (`vendor/`) ইনস্টল
Laravel চলার জন্য `vendor/` ফোল্ডার দরকার।
- cPanel → **Terminal** ওপেন করে প্রোজেক্ট ফোল্ডারে যান:
  ```bash
  cd ~/public_html
  composer install --no-dev --optimize-autoloader
  ```
- *(যদি আপনার শেয়ার্ড হোস্টিংয়ে Composer না থাকে, তবে নিজের কম্পিউটারে `composer install --no-dev --optimize-autoloader` রান করে `vendor/` ফোল্ডার জিপ করে সার্ভারে আপলোড করুন।)*

### ধাপ ৩: `.env` ফাইলে ডাটাবেস সেটআপ
`.env` ফাইলটি আগে থেকেই তৈরি করা আছে (`APP_KEY` অটো-জেনারেটেড)। শুধু MySQL তথ্যগুলো আপডেট করুন:
```env
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_cpanel_db_name
DB_USERNAME=your_cpanel_db_user
DB_PASSWORD=your_cpanel_db_password
```
*(প্রয়োজনে `GEMINI_API_KEY` এবং `PATHAO_PASSWORD` বসাতে পারেন।)*

### ধাপ ৪: phpMyAdmin-এ ডাটাবেস ইমপোর্ট
1. cPanel → **MySQL® Databases** থেকে একটি ডাটাবেস ও ইউজার তৈরি করে **All Privileges** দিন।
2. cPanel → **phpMyAdmin** ওপেন করে আপনার ডাটাবেস সিলেক্ট করুন।
3. **Import** ট্যাবে গিয়ে প্রোজেক্টের `database/budgetchoice_database.sql` ফাইলটি ইমপোর্ট করুন।
   - এতে আপনার **সমস্ত লাইভ ডেটা** (Products, Orders, Customers, Expenses, Accounts, Courier Settings ইত্যাদি) অটোমেটিক ডাটাবেসে বসে যাবে!

### ধাপ ৫: ফোল্ডার পারমিশন (যদি দরকার হয়)
```bash
chmod -R 775 storage bootstrap/cache
```

### ধাপ ৬: লগইন
- আপনার ডোমেইনে ভিজিট করুন (`https://yourdomain.com`)।
- প্রথমবার অ্যাডমিন পাসওয়ার্ড সেটআপ স্ক্রিন আসবে (ইমেইল: `samiulbiswas016@gmail.com`)। নতুন পাসওয়ার্ড সেট করলেই আপনার সমস্ত ডেটাসহ ড্যাশবোর্ড খুলে যাবে!

---

## ৩. লোকাল কম্পিউটারে চালানোর নিয়ম
```bash
composer install
php artisan key:generate
# .env এ DB_* বসান এবং phpMyAdmin এ database/budgetchoice_database.sql ইমপোর্ট করুন
php artisan serve
```
এরপর ব্রাউজারে `http://127.0.0.1:8000` ভিজিট করুন।

---

## ফোল্ডার স্ট্রাকচার
- `app/Models/` — Eloquent Models (`Product`, `Order`, `Customer`, `CourierParcel`, `Expense`, `AppSetting`, `SyncCollection`)
- `app/Http/Controllers/` — `AppAuthController`, `SyncController`, `PathaoCourierController`, `SteadfastCourierController`, `CourierSettingsController`, `AiOrderController`
- `database/migrations/` — Laravel Schema Migrations
- `database/budgetchoice_database.sql` — সম্পূর্ণ লাইভ ডেটাবেস SQL ডাম্প (phpMyAdmin Import-এর জন্য)
- `routes/web.php` & `routes/api.php` — সকল পেজ এবং REST API রাউট
- `public/` — কম্পাইল করা React SPA অ্যাসেট ও `index.php` এন্ট্রিপয়েন্ট

---

## ৪. অটোমেটিক ডেপ্লয়মেন্ট স্ট্যাটাস
- **GitHub Actions Auto-Deploy to cPanel**: সক্রিয় (Active)
- **Last Tested**: 2026-11-19

