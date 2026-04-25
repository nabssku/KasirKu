@echo off
cd /d D:\portofolio\backend_kasirku

start cmd /k php artisan serve --port=8001
start cmd /k php artisan reverb:start
start cmd /k cloudflared tunnel --config C:\Users\INTEL\.cloudflared\jagokasir.yaml run
