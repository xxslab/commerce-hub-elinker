# Changelog

## v1.6 — Allegro/eBay production-hardening, integracja z License Hub

- Allegro: `addShipment` rozwiązuje realny `carrierId` z `GET /order/carriers` (cache per kanał), z fallbackiem na udokumentowane `carrierId=OTHER`+`carrierName`, zamiast wysyłać zgadnięty literał `'INPOST'`.
- eBay: `createShippingFulfillment` wysyła prawdziwe `lineItems` (eBay `lineItemId` z `raw_payload`, nie nasz wewnętrzny id) i konfigurowalny `shippingCarrierCode` zamiast pustej tablicy i zgadniętego kodu przewoźnika.
- Allegro/eBay: `sync()` zwraca prawdziwe liczniki `created`/`updated` (wcześniej zawsze `created=0`); retry HTTP nie próbuje już ponownie na 400/401/403/404/422 (bez sensu na błędach definitywnych).
- InPost: brak ślepego retry na 400/401/403/404/422; błędy 401/422/429/5xx dają konkretny, zrozumiały komunikat zamiast ogólnego "sprawdź logi".
- Nowa integracja: License Hub (`license.dosieci.pl`) — podpisane żądania entitlementów (`POST /api/v1/entitlements/check`), lokalna projekcja stanu konta na `companies`, gating nowych kanałów/synchronizacji/przesyłek (domyślnie wyłączony), ekran Ustawienia → Plan i billing, karta "Konto" na dashboardzie. Zobacz sekcję "Integracja z License Hub" w `CLAUDE.md`.
- Dashboard rozszerzony o sekcje Sprzedaż (dziś/nowe/do wysyłki/wysłane), Integracje (błędy, wyłączone kanały) i InPost (przesyłki/błędy) — wcześniej połowa przekazywanych danych nie była w ogóle renderowana.
- Testy: 74 zaliczone (`php artisan test`), w tym 27 nowych dla entitlementów/gatingu i 24 dla produkcyjnej twardości Allegro/eBay/InPost (patrz v1.5 dla poprzedniej rundy).

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
