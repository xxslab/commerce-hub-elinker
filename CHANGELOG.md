# Changelog

## v1.5 — Wspólna lista zamówień, webhooki WooCommerce, idempotencja InPost

- Naprawiono brakującą relację `CommerceOrder::shipments()` — `/orders` rzucał błędem 500 dla dowolnej firmy z realnymi zamówieniami.
- Naprawiono brak castu `raw_payload` na `array` w `OrderItem` — synchronizacja zamówienia z pozycjami (line_items) kończyła się błędem "Array to string conversion".
- `/orders` pokazuje teraz źródło (badge WooCommerce/Allegro/eBay), kanał, kraj, status płatności, stan przesyłki i numer trackingowy oraz pełny zestaw filtrów (kanał, status płatności, kraj, waluta, zakres dat).
- Szczegóły zamówienia mają sekcję przesyłki: lista przesyłek, pobranie etykiety, odświeżenie/wysłanie trackingu oraz formularz utworzenia przesyłki InPost (kurier lub Paczkomat).
- `SyncSalesChannelOrdersJob` klasyfikuje błędy HTTP według faktycznego providera (`woocommerce_*`/`allegro_*`/`ebay_*`) zamiast zawsze raportować błąd jako WooCommerce.
- `InPostClient::createShipment` jest idempotentny (lock + sprawdzenie istniejącej przesyłki) — podwójne kliknięcie nie tworzy duplikatu; dodano obsługę Paczkomatu (`target_point`).
- WooCommerce ma bezpieczny webhook (`POST /api/webhooks/woocommerce/{salesChannel}`) zweryfikowany podpisem HMAC-SHA256, idempotentny wobec duplikatów i chroniony przed nadpisaniem nowszych danych przez opóźnioną dostawę.
- `WooCommerceClient` i klienci Allegro/eBay nie ponawiają już żądań przy błędach 400/401/403/404/422 (retry miał sens tylko dla błędów przejściowych i rate limitów).
- Usunięto 127 zdublowanych plików leżących płasko w katalogu głównym repo (patrz commit `7dff290`) — poprzedni etap.
- Testy: 23 zaliczone (`php artisan test`), w tym nowe testy idempotencji synchronizacji, webhooka, podwójnego tworzenia przesyłki i klasyfikacji błędów per provider.

## v1.3 — Marketplace OAuth z panelu

- Dodano tabelę `marketplace_app_credentials`.
- Dodano model `MarketplaceAppCredential`.
- Dodano panel `Ustawienia marketplace`.
- Dodano formularz dodawania/edycji danych aplikacji Allegro/eBay.
- Allegro OAuth czyta `client_id`, `client_secret`, `redirect_uri` z bazy, nie z `.env`.
- eBay OAuth czyta `client_id`, `client_secret`, `redirect_uri`, `scopes` z bazy, nie z `.env`.
- Połączony kanał zapisuje w `settings.marketplace_app_credential_id`, której aplikacji użyto.
- Refresh tokenów działa na podstawie aplikacji przypisanej do kanału.

## v1.2 — Marketplace OAuth

- Pierwszy szkielet OAuth Allegro/eBay.

## v1.1 — Panel webowy

- Dodawanie WooCommerce, lista zamówień, szczegóły, InPost actions.

## v1.0 — Core starter

- Modele, migracje, connector WooCommerce, joby i scheduler.
