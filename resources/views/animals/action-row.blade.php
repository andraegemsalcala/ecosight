<div data-action-row class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
    <input type="hidden" name="actions[{{ $index }}][id]" value="{{ $action['id'] ?? '' }}">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="form-label" for="actions_{{ $index }}_title">Title</label>
            <input id="actions_{{ $index }}_title" name="actions[{{ $index }}][title]" value="{{ $action['title'] ?? '' }}" class="form-input" placeholder="Example: Plant native trees">
        </div>
        <div>
            <label class="form-label" for="actions_{{ $index }}_category">Category</label>
            <input id="actions_{{ $index }}_category" name="actions[{{ $index }}][category]" value="{{ $action['category'] ?? '' }}" class="form-input" placeholder="tree planting">
        </div>
        <div>
            <label class="form-label" for="actions_{{ $index }}_difficulty">Difficulty</label>
            <select id="actions_{{ $index }}_difficulty" name="actions[{{ $index }}][difficulty]" class="form-input">
                @foreach (['easy', 'medium', 'hard'] as $difficulty)
                    <option value="{{ $difficulty }}" @selected(($action['difficulty'] ?? 'easy') === $difficulty)>{{ ucfirst($difficulty) }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="form-label" for="actions_{{ $index }}_description">Description</label>
            <textarea id="actions_{{ $index }}_description" name="actions[{{ $index }}][description]" rows="3" class="form-input" placeholder="Explain what the user can do.">{{ $action['description'] ?? '' }}</textarea>
        </div>
    </div>
    <button type="button" data-remove-action class="mt-3 text-sm font-bold text-red-600">Remove row</button>
</div>
