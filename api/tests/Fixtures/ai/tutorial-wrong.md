Understood: the harbour game writes letters, the harbour master signs them, and the goal of the fuel step speaks of the boat. Here is the block:

```ride-changes
{
  "format": "ride-changes/1",
  "game": "{{game}}",
  "base": { "pack": "{{pack}}", "snapshot": "{{snapshot}}", "brief": "{{brief}}" },
  "summary": "The tutorial comes by letter from the harbour master",
  "ops": [
    { "op": "set", "id": "device", "target": { "rules": "tutorial" }, "path": "/messenger/device", "value": "parchment", "unit": "choice" },
    { "op": "set", "id": "sender", "target": { "rules": "tutorial" }, "path": "/messenger/sender", "value": "Harbour master", "unit": "text" },
    { "op": "text", "id": "goal", "target": { "rules": "tutorial" }, "path": "/steps/4/goal", "locale": "en", "value": "Fill up the boat" }
  ]
}
```
