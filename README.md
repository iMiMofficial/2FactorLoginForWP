<!-- Banner/Logo -->
<p align="center">
  <img src="https://raw.githubusercontent.com/iMiMofficial/2FactorLoginForWP/main/assets/img/logo.svg" alt="2Factor Login for WP" width="200"/>
</p>

<h1 align="center">2Factor Login for WP</h1>

<p align="center">
  <b>Modern, secure OTP-based login & signup for WordPress</b><br/>
  <a href="https://wordpress.org/plugins/2factor-login-for-wp/">WordPress.org</a> • <a href="https://github.com/iMiMofficial/2FactorLoginForWP">GitHub</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-5.0%2B-blue"/>
  <img src="https://img.shields.io/badge/Tested%20up%20to-6.8-brightgreen"/>
  <img src="https://img.shields.io/badge/License-GPLv2%2B-blue"/>
  <img src="https://img.shields.io/badge/Stable%20tag-1.0.0-blue"/>
</p>

---

## ✨ Features
- 🔐 **OTP-based Authentication**: Secure login/signup with phone and OTP (no passwords needed)
- 🔄 **WordPress Integration**: Seamless user creation, login, and onboarding
- 👤 **User Role Selection**: Assign any role (default: subscriber) to new users, with admin warning for privileged roles
- 📲 **2Factor API**: Uses your 2Factor.in API key for SMS OTP delivery
- 📝 **Dynamic Onboarding**: Collect email/name after OTP or both (admin configurable)
- 🌍 **Country Code Dropdown**: User can select country code (or use default)
- 🕵️‍♂️ **Privacy-Friendly Usernames**: Truncated phone + random code or full phone (admin option)
- ⚙️ **Admin Settings**: 15+ configurable options, all in a modern tabbed UI
- 🎨 **Custom Redirect & CSS**: Set redirect after login and inject custom styles
- 🗂 **User Profile Integration**: Onboarding data (phone, name, email) visible/editable in admin
- 💎 **Modern UI**: Responsive, accessible, and minimal design
- 🛡 **Security**: Rate limiting, brute force protection, OTP expiry, validation, and enumeration protection
- 🚫 **No test/debug code**: Production-ready, no sensitive data exposed

---

## ⚡ Quick Start
1. **Upload or clone** to `/wp-content/plugins/2factor-login-for-wp/`
2. **Activate** in your WordPress admin
3. **Configure** via <b>Settings → 2Factor Login</b>
4. **Add** `[twofactor_login]` shortcode to any page/post

---

## 🖼 Screenshots

| Screenshot | Preview |
|------------|---------|
| Login Form | ![](https://raw.githubusercontent.com/iMiMofficial/2FactorLoginForWP/main/assets/img/2factor-screenshot-front-end.png) |
| Admin API Settings | ![](https://raw.githubusercontent.com/iMiMofficial/2FactorLoginForWP/main/assets/img/2factor-screenshot-admin-api-settings.png) |
| Admin Customize | ![](https://raw.githubusercontent.com/iMiMofficial/2FactorLoginForWP/main/assets/img/2factor-screenshot-admin-customize.png) |
| Admin Shortcode | ![](https://raw.githubusercontent.com/iMiMofficial/2FactorLoginForWP/main/assets/img/2factor-screenshot-admin-shortcode.png) |
| Admin Advanced | ![](https://raw.githubusercontent.com/iMiMofficial/2FactorLoginForWP/main/assets/img/2factor-screenshot-admin-advanced.png) |
---

## ⚙️ Admin Settings
- **2Factor API Key**: Your API key from [2Factor.in](https://2factor.in)
- **OTP Length**: 4-8 digits
- **OTP Expiry**: 1-15 minutes
- **Allow Country Code Selection**: User can pick country (or use default)
- **Default Country Code**: e.g. +91
- **Require Email/Name**: Toggle onboarding fields
- **When to Collect Fields**: After OTP or Both (before/after)
- **Primary Button Color**: UI customization
- **User Role**: Any WordPress role (with warning for "Administrator")
- **Username Generation**: Truncated (privacy-friendly) or full phone number
- **Redirect URL**: Custom after login
- **Custom CSS**: Style injection
- **Dark Mode, Animations, Accessibility**: All UI is accessible and customizable

---

## 📝 Usage
- Add `[twofactor_login]` shortcode to any page/post for the OTP login/signup form
- The form will show phone (+91 pre-filled or country dropdown), and onboarding fields as per settings
- All actions are AJAX-based, no reloads
- New users are created and logged in automatically
- Existing users log in with OTP

---

## 👤 User Profile (Admin)
- Onboarding data (phone, name, email) is visible and editable in the user profile in admin
- Admins can update user phone, name, and email from the profile page

---

## 🔒 Security & Best Practices
- ⏱ **Rate Limiting**: 1 OTP per minute per phone
- 🚫 **Brute Force Protection**: 3 attempts per OTP, plus IP-based lockout (5 minutes after 3 failed attempts)
- ⏳ **OTP Expiry**: Configurable (default 5 min)
- 💾 **OTP Storage**: Transient with DB fallback for reliability
- 🧹 **Validation**: All fields validated and sanitized
- 🛡 **Nonce Verification**: All AJAX and form actions are nonce-protected
- 🧼 **Output Escaping**: All output is properly escaped
- 🛡 **SQL Injection Safe**: All queries use `$wpdb->prepare()` and `esc_sql()`
- 🕵️‍♂️ **User/Email Enumeration Protection**: Generic error messages for onboarding and user check
- 📁 **No direct file operations**: Uses WP_Filesystem
- 🚫 **No debug/test code in production**
- 🚫 **No direct access to plugin files**
- 🧹 **No unnecessary files in release**

---

## 💡 FAQ
<details>
<summary>Does it support both login and signup?</summary>
Yes! If the phone exists, user logs in. If not, a new user is created.
</details>
<details>
<summary>What if onboarding is disabled?</summary>
Username and email are auto-generated from the phone number.
</details>
<details>
<summary>What about passwords?</summary>
Passwords are randomly generated and not shown to the user. Users log in with OTP.
</details>
<details>
<summary>Can users set a password later?</summary>
Yes, via the default WordPress "Lost your password?" link or admin profile.
</details>
<details>
<summary>Can I use this for WooCommerce or membership sites?</summary>
Yes, it works with any plugin that uses standard WordPress user accounts.
</details>
<details>
<summary>Is it compatible with caching plugins?</summary>
Yes, OTPs are stored in transients with DB fallback for reliability.
</details>
<details>
<summary>Is it GDPR compliant?</summary>
No personal data is sent to 2Factor.in except the phone number for OTP delivery. All data is stored in your WordPress site.
</details>

---

## 🤝 Contributing
Pull requests, issues, and suggestions are welcome! For major changes, please open an issue first to discuss what you would like to change.

1. Fork the repo
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 🙋‍♂️ Author & Support
<p align="center">
  <b>Md Mim Akhtar</b><br/>
  <a href="https://www.imimofficial.com">imimofficial.com</a><br/>
  <a href="https://twitter.com/iMiMofficial">@iMiMofficial</a> • <a href="https://github.com/iMiMofficial">GitHub</a><br/>
  <a href="https://www.patreon.com/iMiMofficial">Patreon</a> • <a href="https://www.buymeacoffee.com/imimofficial">BuyMeACoffee</a> • <a href="https://paypal.me/imimofficial">PayPal</a>
</p>

---

## 🏆 Credits
- **Plugin Author:** Md Mim Akhtar
- **Unofficial:** Not affiliated with 2Factor.in

---

## 📜 License
GPL v2 or later

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, see https://www.gnu.org/licenses/gpl-2.0.html

---

## 📅 Changelog
### 1.0.0
- Initial public release: production-ready, all-in-one plugin 
