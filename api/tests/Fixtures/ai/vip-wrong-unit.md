Lowering the price of the VIP account to 120:

```ride-changes
{
  "format": "ride-changes/1",
  "game": "{{game}}",
  "base": { "pack": "{{pack}}", "snapshot": "{{snapshot}}", "brief": "{{brief}}" },
  "summary": "The VIP account costs 120 coins",
  "ops": [
    { "op": "set", "id": "vip-price", "target": { "rules": "starter" }, "path": "/premium/vip/coins", "value": 120, "unit": "percent" },
    { "op": "set", "id": "vip-days", "target": { "rules": "starter" }, "path": "/premium/vip/days", "value": 5000, "unit": "plain" }
  ]
}
```
