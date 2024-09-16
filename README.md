# Neon Downloader

This is a telegram bot instagram downloader built with pure PHP.

## Prerequisites

- a php configured server with cpanel and phpmyadmin
- a domain

# Installation

1. link your domain to the server
2. head over to telegram `t.me/BotFather` and create your own bot. save bot token which botfather will give you
3. extract neon_downloader folder to your server's public_html folder
4. head over to this link once `https://api.telegram.org/bot{bot_token}/setWebhook?url={yourdomain.com/index.php}` which `{bot_token}` is the token you got from botfather. this will set telegram bot api weebhooks to index.php file inside public_html folder

# Database Configuration

### 1. open phpmyadmin from cpanel
### 2. import neondownloader.sql to create all database tables
### 3. find config table and insert a row with these informations:
```bash
your telegram account id for `developer_tid` and `super_user_tid` columns # you can find your telegram id with the help of t.me/userinfobot
bot token from botfather for `bot_token` column
your telegram bot username for `bot_username` column
```
### 4. from `cpanel / Manage My Databases` create a root user with all priveliages and link it to your database `neondownloader`
### 5. head over to `core/config.php` and change these variables on your neeeds:
```bash
$hostname = "localhost";
$database = "database_name";
$username = "username";
$password = "password";
```

if you've done all steps without errors your bot should be ready to use. head over to your bot on telgram and send `/start/` command 
