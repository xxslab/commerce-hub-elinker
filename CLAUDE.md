# Commerce Hub — instrukcje dla Claude

## Cel projektu

Rozwijaj istniejącą aplikację Laravel Commerce Hub — panel do obsługi zamówień i integracji WooCommerce, Allegro, eBay oraz InPost. Produkcja działa pod `https://elink.dosieci.pl`.

## Stan wdrożenia

- Laravel 8.x / PHP 8.1+.
- Autoryzacja użytkowników, role i izolacja danych firm są wdrożone.
- `/orders` to wspólna lista zamówień WooCommerce + Allegro + eBay (badge źródła, kanał, kraj, status realizacji/płatności, stan przesyłki i tracking, pełny zestaw filtrów).
- WooCommerce ma test połączenia, synchronizację idempotentną (retry z backoffem, klasyfikacja błędów po statusie HTTP), mapowanie statusów, obsługę zamówień oraz zweryfikowany podpisem HMAC webhook (`/api/webhooks/woocommerce/{salesChannel}`), idempotentny wobec duplikatów i out-of-order dostaw.
- Allegro i eBay mają warstwę OAuth, konektory, odświeżanie tokenów i synchronizację; pełne testy live wymagają danych API.
- InPost ma obsługę przesyłek (kurier i Paczkomat), etykiet i trackingu, z ochroną przed podwójnym utworzeniem przesyłki (double-click); UI tworzenia/przeglądu przesyłki jest w szczegółach zamówienia. Pełne testy live wymagają tokena.
- Komunikaty błędów synchronizacji (`SyncSalesChannelOrdersJob`) są przypisane do faktycznego providera (woocommerce_*/allegro_*/ebay_*), nie tylko WooCommerce.
- Migracje, cache i autoloader zostały uruchomione na produkcji.
- Testy aplikacji: 23 zaliczone (`php artisan test`).
- `APP_DEBUG=false`, rejestracja publiczna jest wyłączona.

## Zasady pracy

1. Najpierw przeczytaj `README.md`, `INSTALL.md`, `CHANGELOG.md`, konfigurację, routing, migracje, modele, kontrolery, joby i testy.
2. Nie ujawniaj, nie commituj i nie loguj sekretów: `.env`, haseł, kluczy WooCommerce, tokenów OAuth ani danych InPost.
3. Nie usuwaj danych produkcyjnych, kanałów ani sklepów bez jednoznacznej instrukcji i sprawdzenia zakresu.
4. Każdą zmianę zabezpieczeń sprawdź testem regresyjnym. Szczególnie pilnuj izolacji `company_id`, autoryzacji, CSRF, szyfrowania credentiali i ochrony przed masowym przypisaniem pól.
5. Synchronizacja musi być idempotentna, paginowana, odporna na błędy API i zapisywać wynik do `sync_logs`.
6. Nie mieszaj statusu płatności ze statusem realizacji zamówienia.
7. Dla integracji zewnętrznych używaj kontraktów konektorów, nie dodawaj logiki API bezpośrednio do kontrolerów.
8. Przed zakończeniem uruchom co najmniej `php artisan test`, `php artisan route:list` oraz odpowiednie testy statyczne. Jeśli lokalnie brakuje PHP/Composera, opisz to i sprawdź kod inną dostępną metodą.

## Priorytety dalszego rozwoju

1. Dokończyć produkcyjne testy OAuth Allegro/eBay po otrzymaniu danych aplikacji (kod OAuth/refresh/connect/callback jest gotowy, ale nie zweryfikowany na żywym API).
2. Dodać push trackingu do Allegro (`addShipment`) i eBay (`createShippingFulfillment`) zweryfikowany na żywym API — kod istnieje (`PushTrackingToSourceJob`), ale sprawdzony tylko przez kontrakt/typy, nie przez realną integrację.
3. Rozszerzyć testy o paginację Allegro/eBay z mockami (WooCommerce i webhook mają już testy idempotencji/duplikatów), timeouty i HTTP 403/500.
4. Uporządkować scheduler i worker kolejki w Plesku; obecnie zadania są zdefiniowane, ale wymagają włączenia po stronie hostingu.
5. Rozszerzyć monitoring o alerty, metryki czasu synchronizacji i czytelne komunikaty dla administratora.
6. Przed usunięciem któregokolwiek WordPressa uzyskać dokładne domeny/katalogi, wykonać backup i przygotować plan odtworzenia.

## Prompt startowy dla Claude

> Przejmij rozwój tego repozytorium jako senior Laravel engineer. Najpierw wykonaj audyt aktualnego kodu i porównaj go z `CLAUDE.md`, `README.md`, `CHANGELOG.md` oraz testami. Kontynuuj pracę nad Commerce Hub bez przepisywania działających modułów. Skup się na bezpieczeństwie wielofirmowym, idempotentnej synchronizacji WooCommerce/Allegro/eBay, OAuth, InPost, kolejce, schedulerze i monitoringu. Każdą zmianę implementuj w kodzie, migracjach, widokach i testach, gdy jest potrzebna. Nie używaj prawdziwych sekretów w repozytorium. Po każdej większej części uruchom testy, sprawdź routing i przedstaw: zmienione pliki, ryzyka, wyniki testów oraz czynności wymagające danych zewnętrznych. Nie wykonuj destrukcyjnych operacji na produkcji bez jednoznacznego zakresu.
