@if ($errors->any())
    <div class="mb-4 flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
        <div>
            <div class="font-semibold">Periksa kembali isian Anda</div>
            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
