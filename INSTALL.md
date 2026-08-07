# Commerce Hub v1.1 — instalacja na świeżym Laravelu

Ta paczka jest overlayem do świeżego Laravel 11/12. Nie jest pełnym katalogiem `vendor`, więc najpierw tworzysz normalny projekt Laravel, potem kopiujesz pliki z tej paczki.

## 1. Utwórz projekt

```bash
cd /var/www
composer create-project laravel/laravel commerce-hub
cd commerce-hub
```

## 2. Wgraj overlay

Rozpakuj ZIP gdzieś obok projektu, np. `/var/www/commerce-hub-v1`, a potem:

```bash
cp -R /var/www/commerce-hub-v1/app ./
cp -R /var/www/commerce-hub-v1/config ./
cp -R /var/www/commerce-hub-v1/database ./
cp -R /var/www/commerce-hub-v1/routes ./
cp -R /var/www/commerce-hub-v1/resources ./
```

Albo użyj skryptu:

```bash
bash /var/www/commerce-hub-v1/scripts/install_overlay.sh /var/www/commerce-hub-v1
```

## 3. Ustaw `.env`

Na dev najprościej:

```env
APP_NAME="Commerce Hub"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=commerce_hub
DB_USERNAME=commerce_hub
DB_PASSWORD=strong_password

QUEUE_CONNECTION=sync
CACHE_STORE=file
SESSION_DRIVER=file

INPOST_API_TOKEN=
INPOST_ORGANIZATION_ID=

ALLEGRO_CLIENT_ID=
ALLEGRO_CLIENT_SECRET=
ALLEGRO_REDIRECT_URI=http://127.0.0.1:8000/integrations/allegro/callback

EBAY_CLIENT_ID=
EBAY_CLIENT_SECRET=
EBAY_REDIRECT_URI=http://127.0.0.1:8000/integrations/ebay/callback
```

Na produkcji zmień `QUEUE_CONNECTION=redis` albo `database` i odpal worker.

## 4. Migracje i seed

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed --class=CommerceHubSeeder
php artisan config:clear
php artisan route:clear
```

## 5. Start lokalny

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Wejdź:

```text
http://127.0.0.1:8000
```

## 6. Dodaj pierwszy WooCommerce

W panelu:

```text
Integracje → Dodaj WooCommerce
```

W WooCommerce wygeneruj klucze:

```text
WooCommerce → Ustawienia → Zaawansowane → REST API → Dodaj klucz
Uprawnienia: Read/Write
```

Wpisujesz:

```text
URL sklepu
Consumer Key
Consumer Secret
```

Potem klikasz:

```text
Test
Synchronizuj
```

Jeśli `QUEUE_CONNECTION=sync`, synchronizacja wykona się od razu.
Jeśli `QUEUE_CONNECTION=redis/database`, odpal:

```bash
php artisan queue:work
```

## 7. Scheduler / cron

Na produkcji dodaj:

```cron
* * * * * cd /var/www/commerce-hub && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler odpala:

```text
commerce-hub:sync-orders co 5 minut
commerce-hub:sync-tracking co 15 minut
```

## 8. Co działa teraz

Działa jako fundament:

```text
- panel webowy
- dodanie WooCommerce
- test połączenia WooCommerce
- synchronizacja zamówień WooCommerce
- lista zamówień
- szczegóły zamówienia
- pozycje zamówienia
- lokalna zmiana statusu
- tworzenie shipmentu InPost przez API, po uzupełnieniu tokenów
- pobieranie etykiety InPost
- tracking InPost
- push trackingu jako notatka do WooCommerce
```

Allegro i eBay mają klasy integracyjne i miejsce pod OAuth/API. Następny etap to zrobienie ekranów OAuth i callbacków.


## Allegro/eBay OAuth — v1.2

Po skopiowaniu overlay ustaw w `.env`:

```env
ALLEGRO_CLIENT_ID=
ALLEGRO_CLIENT_SECRET=
ALLEGRO_REDIRECT_URI=https://twoja-domena.pl/integrations/allegro/callback

EBAY_CLIENT_ID=
EBAY_CLIENT_SECRET=
EBAY_REDIRECT_URI=https://twoja-domena.pl/integrations/ebay/callback
```

W panelach developerskich Allegro/eBay wpisz dokładnie te same callback URL.

Po zmianie `.env`:

```bash
php artisan config:clear
php artisan cache:clear
```

W panelu Commerce Hub wejdź:

```text
Integracje -> Połącz Allegro
Integracje -> Połącz eBay
```

Tokeny są zapisywane zaszyfrowane w `sales_channels.credentials_encrypted`.

Scheduler powinien działać z crona systemowego:

```bash
* * * * * cd /var/www/commerce-hub && php artisan schedule:run >> /dev/null 2>&1
```
