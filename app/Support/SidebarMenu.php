<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Turns config/kadca_menu.php into the sidebar tree: a link for each built screen, the "not built yet"
 * page for the rest, the current page lit up and every group above it opened.
 */
class SidebarMenu
{
    public function __construct(private Request $request)
    {
    }

    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return $this->walk(config('kadca_menu', []), []);
    }

    private function walk(array $items, array $trail): array
    {
        $out = [];
        foreach ($items as $item) {
            $path = [...$trail, $item['label']];
            $node = ['label' => $item['label'], 'icon' => $item['icon'] ?? null, 'children' => [], 'active' => false, 'built' => true, 'href' => null];

            if (! empty($item['children'])) {
                $node['children'] = $this->walk($item['children'], $path);
                $node['active'] = collect($node['children'])->contains('active', true);
                $node['built'] = collect($node['children'])->contains('built', true);
            } elseif (! empty($item['route'])) {
                $node['href'] = route($item['route'], $item['params'] ?? []);
                $node['active'] = $this->isActive($item);
            } else {
                $node['built'] = false;
                $node['href'] = route('not-built', ['item' => implode(' › ', $path)]);
                $node['active'] = $this->request->routeIs('not-built') && $this->request->query('item') === implode(' › ', $path);
            }
            $out[] = $node;
        }

        return $out;
    }

    private function isActive(array $item): bool
    {
        if (isset($item['active_param'])) {
            [$name, $value] = $item['active_param'];

            return $this->request->routeIs($item['route']) && $this->request->route($name) === $value;
        }
        if (! isset($item['active'])) {
            return false; // a second link to a screen that another item already lights up
        }
        // One screen under several menu items told apart by query values, as KLAES does (?url=scmore&from=dms).
        foreach ($item['active_query'] ?? [] as $name => $value) {
            if ($this->request->query($name) !== $value) {
                return false;
            }
        }

        return $this->request->routeIs(...(array) $item['active']);
    }
}
