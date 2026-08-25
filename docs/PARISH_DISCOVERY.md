# Parish discovery

## Cel

Tryb discovery dostarcza listę jednostek, które można przekazać później do właściwego acquisition providera. Nie pobiera rekordów genealogicznych i nie tworzy `ExternalIndexRecord`.

## Wspólny model

`AvailableParish`:

```text
provider
providerParishId?
name
regionCode?
regionName?
metadata
```

## Geneteka

Podstawowa strategia:

```text
index.php?op=gt&lang=pol&w=<region>&rid=A
    ↓
<select name="rid">
    ↓
<option value="<rid>">Nazwa parafii</option>
```

Fallback próbuje wykorzystać `parishes` w JSON zwracanym przez `getAct.php`.

Pełne discovery wszystkich regionów najpierw odczytuje `<select name="w">`, a następnie wykonuje discovery osobno dla każdego regionu.

## Metryki-Wołyń

Źródłem discovery jest strona `Zawartość`. Parser odczytuje tabelę zaczynającą się od `Parafia / Parish`, a kolejne wiersze `Wpisy` i `Indeksy` przypisuje do poprzedniej parafii. Nagłówki grup wyznaniowych bez wiersza `Wpisy`/`Indeksy` są pomijane.

## Cache

Surowe strony discovery są przechowywane przez `RawResponseStore`. `--refresh` omija cache.

## CLI

```bash
php bin/mytree-index geneteka --list-parishes --region=06mp
php bin/mytree-index geneteka --list-parishes --all-regions
php bin/mytree-index wolyn --list-parishes
```

Formaty: `table`, `json`, `jsonl`.
