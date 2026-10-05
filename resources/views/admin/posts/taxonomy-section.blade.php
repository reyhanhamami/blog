<section class="card space-y-4" data-taxonomy-quick-create>
    <h2 class="text-lg font-semibold">Klasifikasi</h2>
    @foreach(['categories' => ['Kategori', 'category_id', $categories], 'tags' => ['Tag', 'tags[]', $tags], 'topics' => ['Topik', 'topics[]', $topics]] as $module => [$label, $name, $options])
        <div>
            <div class="article-field-heading"><label class="form-label" for="post-{{ $module }}">{{ $label }}</label>@can($module.'.create')<button type="button" class="article-create-link" data-taxonomy-open="{{ $module }}">+ {{ $label }} baru</button>@endcan</div>
            <select id="post-{{ $module }}" class="form-input" data-search-select data-taxonomy-select="{{ $module }}" data-store-url="{{ route('admin.'.$module.'.store') }}" name="{{ $name }}" @if($module !== 'categories') multiple size="5" @endif>
                @if($module === 'categories')<option value="">—</option>@endif
                @foreach($options as $option)
                    <option value="{{ $option->id }}" @selected($module === 'categories' ? old('category_id', $post->category_id) == $option->id : in_array($option->id, old($module, $post->$module->modelKeys())))>{{ $option->name }}</option>
                @endforeach
            </select>
            @error($name)<p class="error">{{ $message }}</p>@enderror
        </div>
        @if($module === 'categories')
            <div><label class="form-label">Penulis</label><select class="form-input" data-search-select name="author_id"><option value="">—</option>@foreach($authors as $author)<option value="{{ $author->id }}" @selected(old('author_id', $post->author_id) == $author->id)>{{ $author->name }}</option>@endforeach</select></div>
        @endif
    @endforeach
    <p class="text-sm text-green-700" data-taxonomy-success role="status" hidden></p>
    <div><label class="form-label">Quiz setelah artikel</label><select class="form-input" data-search-select name="quiz_id"><option value="">—</option>@foreach($quizOptions as $quiz)<option value="{{ $quiz->id }}" @selected(old('quiz_id', $post->quiz_id) == $quiz->id)>{{ $quiz->title }}</option>@endforeach</select></div>
    <div><label class="form-label">Artikel terkait (manual)</label><select class="form-input" data-search-select name="related_posts[]" multiple size="5">@foreach($relatedOptions as $option)<option value="{{ $option->id }}" @selected(in_array($option->id, old('related_posts', $post->relatedPosts->modelKeys())))>{{ $option->title }}</option>@endforeach</select></div>
</section>
