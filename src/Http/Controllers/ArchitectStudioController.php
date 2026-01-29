<?php

declare(strict_types=1);

namespace CodingSunshine\Architect\Http\Controllers;

use CodingSunshine\Architect\Services\DraftParser;
use CodingSunshine\Architect\Services\StudioContextService;
use CodingSunshine\Architect\Services\UiDriverDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

final class ArchitectStudioController
{
    /**
     * @return View|\Inertia\Response
     */
    public function __invoke(UiDriverDetector $detector, StudioContextService $contextService): mixed
    {
        $driver = config('architect.ui.driver', 'auto');

        if ($driver === 'auto') {
            $driver = $detector->detect();
        }

        if ($driver === 'inertia-react') {
            $props = $contextService->build();
            $draftPath = config('architect.draft_path', base_path('draft.yaml'));
            $props['draft'] = File::exists($draftPath) ? File::get($draftPath) : '';

            return view('architect::studio-standalone', ['architectProps' => $props]);
        }

        $viewName = $this->resolveView($driver);

        return view($viewName);
    }

    public function validate(Request $request, DraftParser $parser): RedirectResponse
    {
        $draftPath = config('architect.draft_path', base_path('draft.yaml'));

        if (! file_exists($draftPath)) {
            return redirect()->route('architect.studio')->with('architect.message', 'Draft file not found.');
        }

        try {
            $parser->parse($draftPath);

            return redirect()->route('architect.studio')->with('architect.message', 'Draft is valid.');
        } catch (\Throwable $e) {
            return redirect()->route('architect.studio')->with('architect.message', 'Validation failed: '.$e->getMessage())->with('architect.error', true);
        }
    }

    private function resolveView(string $driver): string
    {
        return match ($driver) {
            'inertia-react' => view()->exists('architect::studio.inertia-react') ? 'architect::studio.inertia-react' : 'architect::studio',
            'livewire-flux' => view()->exists('architect::studio.livewire-flux') ? 'architect::studio.livewire-flux' : 'architect::studio',
            'livewire-flux-pro' => view()->exists('architect::studio.livewire-flux-pro') ? 'architect::studio.livewire-flux-pro' : 'architect::studio',
            default => 'architect::studio',
        };
    }
}
