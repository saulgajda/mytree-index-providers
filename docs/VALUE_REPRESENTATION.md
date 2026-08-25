# Reprezentacja wartości w indeksach zewnętrznych

`ExternalIndexRecord` zachowuje wartości opublikowane przez serwis indeksacyjny,
ale nie należy ich utożsamiać z wierną transkrypcją oryginalnego aktu.

Obaj obecnie obsługiwani providerzy korzystają z indeksów tworzonych przez ludzi.
Osoby indeksujące mogą przed publikacją przepisywać, transliterować, tłumaczyć
lub normalizować imiona, nazwiska i nazwy miejscowości. Wartość taka jak
`Józef`, `Krzemieniec` albo nazwisko zapisane w polskiej formie może więc być
wiernym zapisem tego, co znajduje się w **indeksie**, ale nie musi być literalną
formą występującą w źródle rosyjsko-, łacińsko- lub ukraińskojęzycznym.

Rekordy tworzone przez `GenetekaProvider` i `WolynMetrykiProvider` zawierają
zatem na najwyższym poziomie obiekt `representation`:

```json
{
  "representation": {
    "kind": "indexer_rendering",
    "scope": "indexed_descriptive_values",
    "verbatim_from_provider": true,
    "original_document_wording_asserted": false,
    "language_hint": "pl",
    "script_hint": "Latn",
    "producer": {
      "type": "human_indexer",
      "provider": "geneteka",
      "indexer_id": "example.indexer"
    },
    "metadata": {
      "may_include": [
        "transcription",
        "transliteration",
        "translation",
        "normalization"
      ]
    }
  }
}
```

## Semantyka

`verbatim_from_provider=true` oznacza, że warstwa acquisition nie wykonuje
dodatkowego semantycznego tłumaczenia ani transliteracji wartości pobranych z
indeksu. Nie oznacza to zachowania HTML portalu bajt po bajcie — białe znaki i
znaczniki HTML mogą już zostać strukturalnie przetworzone do `fields` i `raw`.

`original_document_wording_asserted=false` jest kluczowym rozróżnieniem:
sam rekord indeksu nie stanowi twierdzenia, że w oryginalnym dokumencie
archiwalnym występuje dokładnie taka sama pisownia albo ten sam język.

`language_hint=pl` i `script_hint=Latn` opisują typowy sposób prezentowania
wartości opisowych w obecnie obsługiwanych polskojęzycznych portalach
indeksacyjnych. Są to wskazówki, a nie twierdzenie, że każdy fragment tekstu
swobodnego jest zapisany po polsku.

## Integracja z MyTree

Przyszły importer MyTree powinien zachowywać te metadane reprezentacji podczas
tworzenia `Source`, `Mention` i `Claim`. Jeśli później zostanie pobrany skan i
niezależnie wykonana jego transkrypcja, powinna ona zostać zapisana jako osobna
reprezentacja, np. `verbatim_transcription`, zamiast zastępować reprezentację
utworzoną przez indeksatora.

Dzięki temu `CandidateGenerator` może wykorzystywać znormalizowane polskie
wartości indeksowe jako silne sygnały wyszukiwania, a `ConflictDetector` nie
musi traktować różnic pisowni pomiędzy transkrypcją w języku oryginału a
reprezentacją indeksatora jako natychmiastowej sprzeczności.
