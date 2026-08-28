# MyTree Index Providers

Samodzielny pakiet PHP do pobierania indeksów genealogicznych z:

- **Geneteka** — JSON z wewnętrznego endpointu `getAct.php`, stronicowanie,
- **Metryki-Wołyń** — HTML wyszukiwarki, pobieranie parafii rok po roku.

Pakiet nie zależy od Laravela. Został zaprojektowany tak, aby później można było wykorzystać te same klasy providerów we wtyczce/pakiecie Laravel dla MyTree poprzez podmianę klienta HTTP, checkpointów i writera.

## Wymagania

- PHP 8.2+
- `allow_url_fopen=1` dla wbudowanego klienta HTTP CLI
- brak wymaganych bibliotek zewnętrznych
- brak wymogu `ext-dom`, `curl` i `mbstring`

`composer.json` służy przede wszystkim do autoloadingu PSR-4 przy późniejszym użyciu jako biblioteka. CLI działa również bez `composer install`, poprzez `bootstrap.php`.

## Szybki start

### Geneteka — Imbramowice, małopolskie

```bash
php bin/mytree-index geneteka \
  --region=06mp \
  --parish-id=4812 \
  --parish=Imbramowice \
  --types=birth,marriage,death \
  --output=var/imbramowice
```

Typy wejściowe są kanonicznymi typami MyTree, niezależnymi od oznaczeń konkretnego portalu:

- `birth` — urodzenia,
- `marriage` — śluby,
- `death` — zgony.


### Metryki-Wołyń — Szumsk

```bash
php bin/mytree-index wolyn \
  --parish=Szumsk \
  --from=1731 \
  --to=1943 \
  --output=var/szumsk
```

Metryki-Wołyń jest pobierany **rok po roku**. Jedna odpowiedź może zawierać sekcje:

- `birth`,
- `marriage`,
- `death`,
- `parish_census`.


## Odkrywanie dostępnych parafii

Pakiet udostępnia tryb discovery, który nie pobiera indeksów osób, tylko listę parafii dostępnych w danym portalu. Wynik ma wspólny kontrakt `mytree.available-parish.v1`, dzięki czemu może później zasilać selektor parafii w interfejsie MyTree/Laravel.

### Geneteka — jeden region

```bash
php bin/mytree-index geneteka --list-parishes --region=06mp
```

Dla Geneteki `provider_parish_id` jest wartością `rid`, np.:

```text
REGION  ID    PARISH
------  ----  -----------
06mp    4812  Imbramowice
```

Lista jest pobierana z aktualnego formularza wyszukiwarki Geneteki. Jeżeli struktura formularza ulegnie zmianie, provider ma dodatkowy fallback wykorzystujący pole `parishes` odpowiedzi API `getAct.php`.

Możliwe jest również zebranie wszystkich regionów:

```bash
php bin/mytree-index geneteka --list-parishes --all-regions
```

To wykonuje wiele żądań (po jednym na region), dlatego nadal obowiązuje `--delay-ms`. Do zwykłego użycia lepiej preferować listę dla konkretnego regionu.

### Metryki-Wołyń

```bash
php bin/mytree-index wolyn --list-parishes
```

Provider korzysta ze strony **Zawartość** portalu, więc poza nazwą parafii zachowuje także deklarowane zakresy wpisów i pełnych indeksów dla:

- urodzeń,
- ślubów,
- zgonów,
- spisów parafian.

Przykładowy widok tabelaryczny:

```text
PARISH  BIRTHS     MARRIAGES  DEATHS     CENSUS
------  ---------  ---------  ---------  ------
Szumsk  1731-1926  1739-1943  1741-1939  1857
```

### Format wyniku discovery

Domyślnie wynik jest tabelą. Można uzyskać JSON lub JSONL:

```bash
php bin/mytree-index geneteka --list-parishes --region=06mp --format=json
php bin/mytree-index wolyn --list-parishes --format=jsonl
```

Można też zapisać wynik do pliku:

```bash
php bin/mytree-index wolyn --list-parishes --format=json --save=parishes-wolyn.json
```

Przykładowy rekord:

```json
{
  "schema": "mytree.available-parish.v1",
  "provider": "geneteka",
  "provider_parish_id": "4812",
  "name": "Imbramowice",
  "region_code": "06mp",
  "region_name": "Małopolskie",
  "metadata": {}
}
```

Dla Metryki-Wołyń `provider_parish_id` jest `null`, ponieważ wyszukiwarka identyfikuje parafię tekstową nazwą. Zakresy dostępności znajdują się w `metadata.wpisy` i `metadata.indeksy`.

Discovery używa własnego cache surowych stron. Domyślnie jest to `var/discovery-cache`; można wskazać inne miejsce przez `--output=DIR`. Aby odświeżyć listę z portalu, użyj:

```bash
--refresh
```

## Rate limiting

Domyślnie program czeka co najmniej 2000 ms między kolejnymi żądaniami sieciowymi:

```bash
--delay-ms=2000
```

Nie zaleca się zmniejszania tego opóźnienia. Narzędzie jest przeznaczone do kontrolowanego, osobistego pozyskiwania danych. Przed większym pobieraniem należy upewnić się, że sposób użycia jest zgodny z zasadami/regulaminem danego serwisu.

## Wznawianie

Program zapisuje checkpoint po każdej kompletnej jednostce pracy:

- Geneteka — po stronie wyników,
- Metryki-Wołyń — po roku.

Ponowne uruchomienie tego samego polecenia z tym samym `--output` wznowi pracę.

Surowe odpowiedzi są zapisywane w `raw/`. Jeżeli odpowiedź została pobrana, ale proces przerwał się przed checkpointem, przy wznowieniu program użyje lokalnego cache zamiast ponownie pytać serwis.

`records.jsonl` deduplikuje rekordy po `provider_record_id`, dzięki czemu przerwanie w środku jednostki nie powinno tworzyć duplikatów po wznowieniu.

### Pełne pobranie od nowa

```bash
... --restart
```

`--restart`:

- usuwa `records.jsonl`,
- usuwa checkpointy,
- ignoruje istniejący cache w czasie pobierania,
- nadpisuje odpowiadające pliki raw nową odpowiedzią.

## Struktura wyjścia

```text
var/imbramowice/
├── manifest.json
├── records.jsonl
├── state/
│   └── checkpoints.json
└── raw/
    └── geneteka/
        ├── 06mp_4812_B_0.json
        ├── 06mp_4812_B_0.json.meta.json
        └── ...
```

Dla Wołynia:

```text
raw/wolyn-metryki/
├── szumsk_1731.html
├── szumsk_1731.html.meta.json
└── ...
```

## Format `ExternalIndexRecord`

Każda linia JSONL ma stabilny kontrakt:

```json
{
  "schema": "mytree.external-index-record.v1",
  "provider": "wolyn-metryki",
  "provider_record_id": "...",
  "record_type": "birth",
  "parish": "Szumsk",
  "year": 1835,
  "fields": {},
  "raw": {},
  "provenance": {}
}
```

Najważniejsza zasada: `fields` ułatwia dalszą pracę, ale `raw` zachowuje oryginalne wartości indeksu. Narzędzie nie próbuje rozstrzygać niepewności typu `20?`, wariantów nazwiska ani semantyki tekstu w uwagach.

## Provenance

Każdy rekord zawiera m.in.:

- URL żądania,
- czas pobrania,
- ścieżkę do surowej odpowiedzi,
- SHA-256 surowej odpowiedzi,
- parametr parafii/regionu,
- indeks wiersza/strony lub roku.

Dzięki temu późniejszy importer MyTree może utworzyć `Source`, `SourceLocator`, `Mention` i `Claim` bez utraty informacji o pochodzeniu.

## Testy

```bash
php tests/run.php
```

lub po `composer install`:

```bash
composer test
```

## Integracja z Laravel/MyTree

Zobacz [docs/LARAVEL_INTEGRATION.md](docs/LARAVEL_INTEGRATION.md).
