# Commerce Hub — instrukcje dla Claude

## Cel projektu

Rozwijaj istniejącą aplikację Laravel Commerce Hub — panel do obsługi zamówień i integracji WooCommerce, Allegro, eBay oraz InPost. Produkcja działa pod `https://elink.dosieci.pl`.

## Stan wdrożenia

- Laravel 8.x / PHP 8.1+.
- Autoryzacja użytkowników, role i izolacja danych firm są wdrożone.
- WooCommerce ma test połączenia, synchronizację idempotentną, mapowanie statusów i obsługę zamówień.
- Allegro i eBay mają warstwę OAuth, konektory, odświeżanie tokenów i synchronizację; pełne testy live wymagają danych API.
- InPost ma obsługę przesyłek, etykiet i trackingu; pełne testy live wymagają tokena.
- Migracje, cache i autoloader zostały uruchomione na produkcji.
- Testy aplikacji: 9 zaliczonych.
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

1. Dokończyć produkcyjne testy OAuth Allegro/eBay po otrzymaniu danych aplikacji.
2. Dodać testy kontraktowe konektorów i testy synchronizacji z mockami paginacji, timeoutów, rate limitów oraz duplikatów webhooków.
3. Uporządkować scheduler i worker kolejki w Plesku; obecnie zadania są zdefiniowane, ale wymagają włączenia po stronie hostingu.
4. Dodać bezpieczne zarządzanie webhookami WooCommerce i retry z backoffem.
5. Rozszerzyć monitoring o alerty, metryki czasu synchronizacji i czytelne komunikaty dla administratora.
6. Przed usunięciem któregokolwiek WordPressa uzyskać dokładne domeny/katalogi, wykonać backup i przygotować plan odtworzenia.

## Prompt startowy dla Claude

> Przejmij rozwój tego repozytorium jako senior Laravel engineer. Najpierw wykonaj audyt aktualnego kodu i porównaj go z `CLAUDE.md`, `README.md`, `CHANGELOG.md` oraz testami. Kontynuuj pracę nad Commerce Hub bez przepisywania działających modułów. Skup się na bezpieczeństwie wielofirmowym, idempotentnej synchronizacji WooCommerce/Allegro/eBay, OAuth, InPost, kolejce, schedulerze i monitoringu. Każdą zmianę implementuj w kodzie, migracjach, widokach i testach, gdy jest potrzebna. Nie używaj prawdziwych sekretów w repozytorium. Po każdej większej części uruchom testy, sprawdź routing i przedstaw: zmienione pliki, ryzyka, wyniki testów oraz czynności wymagające danych zewnętrznych. Nie wykonuj destrukcyjnych operacji na produkcji bez jednoznacznego zakresu.
