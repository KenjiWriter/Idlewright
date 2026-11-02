Sorry: the price is counted in plain coins, and 5000 days was a slip. The days of the VIP stay as they are. Corrected block:

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
