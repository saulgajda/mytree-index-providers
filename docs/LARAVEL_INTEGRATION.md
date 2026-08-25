# Integracja z Laravel / MyTree

Pakiet celowo rozdziela cztery odpowiedzialności:

```text
Provider
  ├── HttpClientInterface
  ├── CheckpointStoreInterface
  ├── RawResponseStore
  └── RecordWriterInterface
```

Dzięki temu provider nie zna Eloquent, Redis, Laravel HTTP Client ani modeli MyTree.

## Proponowana przyszła wtyczka

```text
mytree-source-index-plugin/
├── src/
│   ├── MyTreeSourceIndexServiceProvider.php
│   ├── Http/LaravelHttpClient.php
│   ├── Storage/LaravelCheckpointStore.php
│   ├── Writer/MyTreeExternalIndexWriter.php
│   ├── Console/AcquireGenetekaCommand.php
│   ├── Console/AcquireWolynCommand.php
│   └── Jobs/AcquireIndexBatch.php
└── composer.json
```

### `LaravelHttpClient`

Implementuje `HttpClientInterface` i wewnętrznie używa `Illuminate\Support\Facades\Http` albo wstrzykniętego `Illuminate\Http\Client\Factory`.

### `LaravelCheckpointStore`

Implementuje `CheckpointStoreInterface`. Możliwe backendy:

- tabela PostgreSQL — najlepsza do audytu,
- Cache/Redis — szybsze, ale mniej trwałe,
- Laravel Storage — najbliższe aktualnej wersji CLI.

### `MyTreeExternalIndexWriter`

Implementuje `RecordWriterInterface` i zapisuje `ExternalIndexRecord` do warstwy stagingowej MyTree, **nie bezpośrednio do `Person`**.

Proponowany przepływ:

```text
ExternalIndexRecord
    ↓
Acquisition/Staging record
    ↓
Source importer
    ↓
Source
Mention
Claim
SourceLocator
```

Warstwa stagingowa powinna zachować `provider_record_id`, `raw`, `fields` i pełne `provenance`.

## Service container

Przykładowe wiązania w przyszłym pakiecie:

```php
$this->app->bind(HttpClientInterface::class, LaravelHttpClient::class);
$this->app->bind(CheckpointStoreInterface::class, LaravelCheckpointStore::class);
```

Same `GenetekaProvider` i `WolynMetrykiProvider` pozostają niezmienione.

## Kolejki

Dla większych importów naturalnym krokiem będzie rozbijanie pracy na małe joby:

- Geneteka: `(region, rid, type, page)`,
- Metryki-Wołyń: `(parish, year)`.

To odpowiada jednostkom checkpointów używanym już przez narzędzie CLI.

## Idempotencja

`provider_record_id` powinien mieć unikalny indeks w tabeli stagingowej. Dzięki temu retry joba nie utworzy duplikatu.

## Granica odpowiedzialności

Provider ma pozyskiwać i wiernie reprezentować indeks. Nie powinien:

- scalać osób,
- tworzyć hipotez tożsamości,
- normalizować wariantów nazw jako „prawdę”,
- traktować miejsca zdarzenia jako miejsca zamieszkania,
- interpretować tekstu uwag jako pewnych relacji bez osobnej warstwy ekstrakcji.


## Discovery parafii

Providerzy udostępniają również warstwę discovery niezależną od pobierania rekordów:

```php
$parishes = $geneteka->listParishes('06mp');
$parishes = $wolyn->listParishes();
```

Każdy element jest `AvailableParish` i ma wspólny kontrakt:

```text
provider
providerParishId
name
regionCode
regionName
metadata
```

W przyszłej wtyczce Laravel naturalne zastosowanie to:

```text
Provider selection
    ↓
region (jeśli wymagany)
    ↓
AvailableParish[]
    ↓
Filament Select / searchable relation-like picker
    ↓
konfiguracja zadania acquisition
```

Lista discovery nie powinna być wiązana z modelem `Person`. Może być cachowana osobno (np. tabela `external_provider_parishes` lub Laravel Cache), wraz z `retrieved_at` i surowym payloadem dla audytu.

Dla Geneteki warto przechowywać `region_code + provider_parish_id` jako klucz zewnętrzny. Dla Metryki-Wołyń obecnie kluczem wejściowym jest nazwa parafii, więc lokalny rekord discovery powinien zachować również dokładną pisownię zwróconą przez portal.
