assuming php 8.2 version,composer package manager,postgres is already installed in your system
composer install
cp .env.example .env
php artisan key:generate

this project uses postgres db so create a postgres db in your system named seat_allocation_system

php artisan migrate
php artisan db:seed --class=ExamSeeder
