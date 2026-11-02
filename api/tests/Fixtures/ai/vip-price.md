The VIP account costs 150 coins now. Lowering it to 120:

```ride-changes
{
  "format": "ride-changes/1",
  "game": "{{game}}",
  "base": { "pack": "{{pack}}", "snapshot": "{{snapshot}}", "brief": "{{brief}}" },
  "summary": "The VIP account costs 120 coins",
  "ops": [
    { "op": "set", "id": "vip-price", "target": { "rules": "starter" }, "path": "/premium/vip/coins", "value": 120, "unit": "plain" }
  ]
}
```
