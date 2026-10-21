<?php

namespace App\Http\Controllers;

use App\Enums\EducationalStage;
use App\Http\Requests\StoreSchoolClassRequest;
use App\Http\Requests\UpdateSchoolClassRequest;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    /**
     * Display a listing of classes and their sections.
     */
    public function index(Request $request): Response
    {
        if (! $request->user()?->can('classes.view')) {
            abort(403, 'Unauthorized to view academic classes and sections.');
        }

        $classes = SchoolClass::query()
            ->with(['sections' => fn ($query) => $query->withCount('enrollments')->orderBy('name')])
            ->withCount(['enrollments', 'subjects', 'teacherSubjectAssignments'])
            ->orderBy('level_order')
            ->get();

        $stats = [
            'total_classes' => $classes->count(),
            'total_sections' => Section::count(),
            'total_stages' => $classes->pluck('stage')->unique()->count(),
        ];

        // Gather preset stages and any custom stages already created
        $presetStages = array_map(fn (EducationalStage $stage) => [
            'value' => $stage->value,
            'label' => $stage->label(),
        ], EducationalStage::cases());

        $existingStages = $classes->pluck('stage')->unique();
        $stagesMap = [];
        foreach ($presetStages as $preset) {
            $stagesMap[$preset['value']] = $preset['label'];
        }
        foreach ($existingStages as $st) {
            if (! isset($stagesMap[$st])) {
                $stagesMap[$st] = EducationalStage::formatLabel($st);
            }
        }

        $stages = [];
        foreach ($stagesMap as $val => $lbl) {
            $stages[] = [
                'value' => $val,
                'label' => $lbl,
            ];
        }

        return Inertia::render('academic/classes/Index', [
            'classes' => $classes,
            'stats' => $stats,
            'stages' => $stages,
        ]);
    }

    /**
     * Store a newly created class in storage.
     */
    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        /** @var array{name: string, stage: string, level_order?: int|null, position_mode?: string|null, relative_class_id?: int|null, create_sections?: bool, initial_sections?: string|null, section_capacity?: int|null} $validated */
        $validated = $request->validated();

        $createSections = $validated['create_sections'] ?? false;
        $capacity = $validated['section_capacity'] ?? 50;
        $initialSectionsInput = $validated['initial_sections'] ?? null;
        $targetOrder = $this->resolveTargetOrder($validated);

        DB::transaction(function () use ($validated, $targetOrder, $createSections, $capacity, $initialSectionsInput) {
            SchoolClass::query()
                ->where('level_order', '>=', $targetOrder)
                ->increment('level_order');

            $class = SchoolClass::create([
                'name' => $validated['name'],
                'stage' => $validated['stage'],
                'level_order' => $targetOrder,
            ]);

            SchoolClass::resequence();

            if ($createSections && ! empty($initialSectionsInput)) {
                $sectionNames = array_filter(
                    array_map('trim', explode(',', $initialSectionsInput)),
                    fn ($name) => $name !== ''
                );

                foreach (array_unique($sectionNames) as $secName) {
                    Section::create([
                        'class_id' => $class->id,
                        'name' => $secName,
                        'capacity' => $capacity,
                    ]);
                }
            }
        });

        return redirect()->back()->with('success', "Academic class '{$validated['name']}' registered successfully.");
    }

    /**
     * Update the specified class in storage.
     */
    public function update(UpdateSchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        /** @var array{name: string, stage: string, level_order?: int|null, position_mode?: string|null, relative_class_id?: int|null} $validated */
        $validated = $request->validated();
        $targetOrder = $this->resolveTargetOrder($validated, $class->id);

        DB::transaction(function () use ($class, $validated, $targetOrder) {
            if ($class->level_order !== $targetOrder) {
                SchoolClass::query()
                    ->where('id', '!=', $class->id)
                    ->where('level_order', '>=', $targetOrder)
                    ->increment('level_order');

                $class->level_order = $targetOrder;
            }

            $class->name = $validated['name'];
            $class->stage = $validated['stage'];
            $class->save();

            SchoolClass::resequence();
        });

        return redirect()->back()->with('success', "Academic class '{$class->name}' updated successfully.");
    }

    /**
     * Remove the specified class from storage.
     */
    public function destroy(Request $request, SchoolClass $class): RedirectResponse
    {
        if (! $request->user()?->can('classes.delete')) {
            abort(403, 'Unauthorized to delete academic classes.');
        }

        if (! $class->canBeDeleted()) {
            return redirect()->back()->with('error', 'Cannot delete this class because it contains active student enrollments, assigned subjects, or teacher allocations.');
        }

        $className = $class->name;

        DB::transaction(function () use ($class) {
            $class->delete();
            SchoolClass::resequence();
        });

        return redirect()->back()->with('success', "Academic class '{$className}' deleted successfully.");
    }

    /**
     * Resolve target progression level order from position directives.
     *
     * @param  array{name: string, stage: string, level_order?: int|null, position_mode?: string|null, relative_class_id?: int|null}  $validated
     */
    private function resolveTargetOrder(array $validated, ?int $currentClassId = null): int
    {
        $positionMode = $validated['position_mode'] ?? 'custom';
        $relativeClassId = isset($validated['relative_class_id']) ? (int) $validated['relative_class_id'] : null;
        $providedOrder = isset($validated['level_order']) ? (int) $validated['level_order'] : null;

        if ($positionMode === 'start') {
            return 1;
        }

        if ($positionMode === 'end') {
            $maxOrder = SchoolClass::query()
                ->when($currentClassId, fn ($q) => $q->where('id', '!=', $currentClassId))
                ->max('level_order');

            return ($maxOrder !== null ? (int) $maxOrder : 0) + 1;
        }

        if ($positionMode === 'before' && $relativeClassId) {
            $relative = SchoolClass::find($relativeClassId);
            if ($relative) {
                return (int) $relative->level_order;
            }
        }

        if ($positionMode === 'after' && $relativeClassId) {
            $relative = SchoolClass::find($relativeClassId);
            if ($relative) {
                return (int) $relative->level_order + 1;
            }
        }

        if ($providedOrder !== null) {
            return max(1, $providedOrder);
        }

        $maxOrder = SchoolClass::query()
            ->when($currentClassId, fn ($q) => $q->where('id', '!=', $currentClassId))
            ->max('level_order');

        return ($maxOrder !== null ? (int) $maxOrder : 0) + 1;
    }
}
