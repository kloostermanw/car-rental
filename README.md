# car-rental

## Installation
Switch to your repo directory. (Can be different for your setup.)
```
cd ~/repo
```

Clone the github repo.
```
git clone https://github.com/kloostermanw/car-rental car-rental
```

```
cd car-rental
```

Create local dev environment
```
docker-compose up -d --build
```

Start shell inside docker
```
docker-compose exec php /bin/bash
```

Run the following command inside this shell.
```
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

Access the application on the following url

http://127.0.0.1:8080/