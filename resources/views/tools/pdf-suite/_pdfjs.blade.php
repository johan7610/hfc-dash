{{-- PDF.js loader — pinned to 4.x via cdnjs. Sets window.pdfjsLib + worker src. --}}
@once
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.min.js"></script>
<script>
    if (window['pdfjsLib']) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.0.379/pdf.worker.min.js';
    }
</script>
@endonce
