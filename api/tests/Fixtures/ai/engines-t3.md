Understood. To confirm: all engines of tier 3 (`slot` engine, `tier` 3), 12% more power, nothing else (turbochargers are another slot and are left alone). Here is the block:

```ride-changes
{
  "format": "ride-changes/1",
  "game": "{{game}}",
  "base": { "pack": "{{pack}}", "snapshot": "{{snapshot}}", "brief": "{{brief}}" },
  "summary": "Engines of tier 3: 12% more power",
  "ops": [
    {
      "op": "scale",
      "id": "t3-power",
      "select": { "kind": "part", "where": { "slot": "engine", "tier": 3 } },
      "path": "/stats/power_kw",
      "percent": 12
    }
  ]
}
```
