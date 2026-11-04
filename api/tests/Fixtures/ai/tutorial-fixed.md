You are right: the letter is called letter, and a step is named by its key. Here is the whole block again:

```ride-changes
{
  "format": "ride-changes/1",
  "game": "{{game}}",
  "base": { "pack": "{{pack}}", "snapshot": "{{snapshot}}", "brief": "{{brief}}" },
  "summary": "The tutorial comes by letter from the harbour master",
  "ops": [
    { "op": "set", "id": "device", "target": { "rules": "tutorial" }, "path": "/messenger/device", "value": "letter", "unit": "choice" },
    { "op": "set", "id": "sender", "target": { "rules": "tutorial" }, "path": "/messenger/sender", "value": "Harbour master", "unit": "text" },
    { "op": "text", "id": "goal", "target": { "rules": "tutorial" }, "path": "/steps/refuel/goal", "locale": "en", "value": "Fill up the boat" }
  ]
}
```
