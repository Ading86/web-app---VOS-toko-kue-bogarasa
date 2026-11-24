<div id="confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-stone-900/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
    <div class="card w-full max-w-sm p-6 shadow-xl">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gold-100 text-gold-700"><x-icon name="alert" class="h-5 w-5" /></div>
            <div>
                <h3 class="text-base font-semibold text-stone-900">Konfirmasi</h3>
                <p id="confirm-text" class="mt-1 text-sm text-stone-600"></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" id="confirm-no" class="btn-secondary">Batal</button>
            <button type="button" id="confirm-yes" class="btn-danger">Ya, lanjutkan</button>
        </div>
    </div>
</div>
<script>
(function () {
    const modal = document.getElementById('confirm-modal');
    let pending = null;
    const open = (form, text) => { pending = form; document.getElementById('confirm-text').textContent = text; modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { pending = null; modal.classList.add('hidden'); modal.classList.remove('flex'); };

    document.addEventListener('submit', function (e) {
        const f = e.target;
        if (f.dataset.confirm && f.dataset.confirmed !== '1') { e.preventDefault(); open(f, f.dataset.confirm); return; }
        if (f.dataset.nolock) return;
        if (f.dataset.submitting === '1') { e.preventDefault(); return; }   // cegah double submit
        f.dataset.submitting = '1';
        f.querySelectorAll('button[type=submit]').forEach(b => { b.disabled = true; b.classList.add('opacity-60'); });  // loading state
    });
    document.getElementById('confirm-yes').onclick = function () { if (pending) { const f = pending; f.dataset.confirmed = '1'; close(); f.requestSubmit(); } };
    document.getElementById('confirm-no').onclick = close;
    window.addEventListener('pageshow', e => { if (e.persisted) location.reload(); });
})();
</script>
