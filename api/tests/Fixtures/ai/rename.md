Understood: the Polish name of the Rdzak GT-4 becomes "Rdzak GT-4 Turbo", and I give the French one too. Here is the block:

```ride-changes
{
  "format": "ride-changes/1",
  "game": "{{game}}",
  "base": { "pack": "{{pack}}", "snapshot": "{{snapshot}}", "brief": "{{brief}}" },
  "summary": "Rdzak GT-4 is called Rdzak GT-4 Turbo",
  "ops": [
    { "op": "text", "id": "pl", "target": { "kind": "vehicle", "slug": "rdzak-gt-4" }, "path": "/name", "locale": "pl", "value": "Rdzak GT-4 Turbo" },
    { "op": "text", "id": "fr", "target": { "kind": "vehicle", "slug": "rdzak-gt-4" }, "path": "/name", "locale": "fr", "value": "Rdzak GT-4 Turbo" }
  ]
}
```
