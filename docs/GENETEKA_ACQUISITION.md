# Geneteka — zapytania, dostępność i granice odpowiedzialności

## Cel

`GenetekaProvider` ma wiernie odwzorowywać możliwości konkretnego providera, a nie planować badania MyTree.

Warstwa biblioteki odpowiada za:

```text
provider form/query
→ HTTP
→ pagination
→ raw cache/checkpoint
→ ExternalIndexRecord
```

Warstwa wyższa (np. Laravel/MyTree) może później odpowiadać za:

```text
availability
→ podział pracy na przedziały
→ wybór providerów
→ kolejność zapytań
→ budżet/strategię akwizycji
```

## Jedno zapytanie = jeden RecordType

Geneteka prezentuje osobne stany formularza dla:

```text
birth
marriage
death
```

Dlatego `GenetekaAcquisition` reprezentuje dokładnie jeden `RecordType`.

```php
$provider
    ->acquisition()
    ->region('06mp')
    ->parish('4812', 'Imbramowice')
    ->recordType(RecordType::Birth)
    ->years(1853, 1860)
    ->acquire($writer);
```

## Fluent API

Builder jest niemutowalny: każda metoda zwraca nową konfigurację.

Zweryfikowane skróty:

```text
person()          → search_lastname / search_name
secondPerson()    → search_lastname2 / search_name2
fromYear()        → from_date
toYear()          → to_date
years()           → from_date + to_date
exact()           → exac=1
excludeParents()  → parents=1
```

Nie należy zgadywać nazw parametrów niezbadanych opcji formularza. Do ich stopniowego dodawania służy `formParameter()`, a po potwierdzeniu semantyki można dodać typowaną metodę fluent.

Parametry transportowe kontrolowane przez provider są zastrzeżone:

```text
bdm
w
rid
length
start
```

## Cache i checkpointy

Fingerprint zapytania zależy od:

```text
region
provider parish id (rid)
record type
wszystkie parametry formularza
page size
```

Dzięki temu dwa różne zapytania nie współdzielą checkpointów ani stron raw cache.

`recordsFiltered` jest używane do obliczenia liczby stron, gdy API je zwraca; `recordsTotal` pozostaje fallbackiem.

## Dostępność

Geneteka może publikować nieciągłe zakresy, np.:

```text
1645
1654
1656
1658-1862
1868-1907
```

Nie należy spłaszczać ich do `1645-1907`, bo utracilibyśmy informację o lukach.

Provider odczytuje te metadane z HTML publicznego interfejsu Geneteki. Nie zakłada istnienia osobnego, stabilnego endpointu API dla coverage.

`discoverAvailability()` zwraca `GenetekaRecordAvailability[]` z:

```text
RecordType
providerParishId (rid właściwy dla typu)
YearRange[]
sourceUrl
```

Te dane są metadanymi providera. Nie są same w sobie planem akwizycji.

## RID per typ

Linki zakładek Geneteki mogą używać różnych `rid` dla urodzeń, małżeństw i zgonów tej samej logicznej parafii. Z tego powodu wyższa warstwa nie powinna zakładać:

```text
one parish = one rid for all record types
```

Provider discovery zachowuje identyfikatory osobno per `RecordType`.

## Przyszła unifikacja

To repozytorium nie wprowadza wspólnego, generycznego Acquisition Managera. Provider-specific fluent API może pokrywać pełną powierzchnię formularza danego serwisu.

Dopiero wyższa warstwa może mapować wspólny zamiar badawczy, np.:

```text
find birth record of person X in interval Y
```

na konkretne parametry Geneteki, Metryki-Wołyń lub kolejnych providerów.
