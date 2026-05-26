---
name: debug-livewire
description: Use this skill when debugging Livewire component errors such as undefined variables, computed property access issues, stale state, polling problems, or blade rendering errors. Also use when the user reports "variável não definida", "undefined variable", or unexpected blank/broken UI in a Livewire view.
---

You are debugging a Livewire 3 component in a Laravel application.

## Core rule — Computed vs Public Properties in Blade

In Livewire 3, `#[Computed]` methods are NOT injected as blade variables automatically.

| Declared as | Blade access (inline) | Inside `@php` block |
|---|---|---|
| `public string $name;` | `{{ $name }}` | `$name` |
| `#[Computed] public function posts()` | `{{ $this->posts }}` | ❌ `$this` not available |

**Critical**: `$this` is NOT available inside `@php ... @endphp` blocks — it causes "Using $this when not in object context". This includes any access to computed properties via `$this->` inside those blocks.

**Correct pattern**: Explicitly pass all computed properties from `render()` using `with()`:

```php
public function render()
{
    return view('livewire.dashboard', [
        'posts'  => $this->posts,
        'count'  => $this->count,
    ]);
}
```

This makes `$posts` and `$count` available as regular blade variables everywhere — inline expressions, `@php` blocks, directives — with no `$this` ambiguity.

The `?? fallback` silences undefined variable errors for scalars but does NOT fix collection methods like `->isEmpty()`, `->count()`, or `->max()`.

## Debugging checklist

### 0. FIRST — Verify the route actually loads the Livewire component

Before debugging the component or blade, confirm the route invokes the Livewire lifecycle. Open the error page → look at the "Routing" section:

| controller shown | What it means |
|---|---|
| `Illuminate\Routing\ViewController` | `Route::view(...)` — Livewire component is NEVER instantiated. `render()` and `with([...])` are dead code. |
| `App\Livewire\YourComponent` | OK — full Livewire lifecycle runs. |

If the route is `Route::view('foo', 'bar')`, change it to `Route::get('foo', \App\Livewire\YourComponent::class)`. No amount of `render()` tweaks fixes a view-only route.

**Also confirm which routes file is actually loaded.** Check `bootstrap/app.php` — the `web:` key under `withRouting()` points to the active file. Duplicate route definitions in unused files (`routes/web.php` vs `routes/default_routes_web.php`) silently mislead.

### 1. Identify the variable source
- Is it a `public $property` on the component? → Use `$variable` in blade.
- Is it a `#[Computed]` method? → Pass it in `render()->with([...])` and use `$variable` in blade. Never use `$this->` inside `@php` blocks.
- Is it passed via `render()->with([...])` ? → Use `$variable` in blade.

### 2. Spot masked errors
Look for `?? 0`, `?? []`, `?? ''` in blade — these may be hiding actual undefined variable bugs rather than legitimately handling null. Replace with the correct `$this->` access.

### 3. Check computed property caching
`#[Computed]` caches the result per request. If the value seems stale:
- Ensure nothing calls `unset($this->computedProp)` unexpectedly.
- If `persist: true` is set, the value is cached across requests (in session) — verify that's intentional.
- To bust the cache manually during a Livewire action: `unset($this->computedProp);`

### 4. Validate return types
- Computed properties returning a Collection: safe to call `->isEmpty()`, `->count()`.
- Computed properties returning a raw DB result (`DB::table()->get()`): also returns a Collection — safe.
- Computed properties returning `?int` or `?float`: always guard with `!== null` before arithmetic.

### 5. Polling and reactivity
- `wire:poll.Xs` re-renders the component on an interval — computed property caches are busted each render cycle.
- If data looks stale in polling context, verify the query isn't being cached at the DB layer (query cache, ORM caching).

### 6. Common Livewire 3 pitfalls
- Calling `$this->computedProp` inside another `#[Computed]` method is valid and safe — the cache is shared.
- Livewire does NOT re-run computed properties on every blade `$this->prop` access within the same render — it runs once and caches.
- `wire:navigate` preserves component state; a hard navigation resets it.

## How to apply this skill

1. Read the Livewire component class and note every `#[Computed]` method name.
2. Check `render()` — if computed properties are not passed via `with([...])`, that is the root cause.
3. Add all needed computed properties to `render()->with([...])`.
4. Read the blade and replace every `$this->name` (especially inside `@php` blocks) with `$name`.
5. Check for `??` fallbacks hiding real errors — remove and use proper variable access.
6. Verify return type contracts (nullable, Collection vs array) against how the blade consumes them.
7. **Always clear view cache before testing**: `php artisan view:clear`. Stale compiled blades can mask or reproduce errors after a fix.
8. **Verify by hitting the real URL** with `curl` and the user's session cookie (extract from the Laravel error page Headers section). Check `STATUS=200` and grep response for `Undefined` / `ErrorException`. `Livewire::test()` does NOT exercise the route layer — it skips middleware and route resolution, so it cannot catch `Route::view()` misconfiguration. Only an HTTP request hits the full stack.
9. Report what was wrong and what was changed.
