<!doctype html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Undian Doorprize — eRAT</title>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    <style>
        :root { color-scheme: dark; }
        body { min-height: 100vh; background: #09090b; color: #f4f4f5; }
        .dp-header { border-bottom: 1px solid #27272a; background: rgba(9, 9, 11, .88); backdrop-filter: blur(12px); }
        .dp-shell { max-width: 1280px; margin: 0 auto; }
        .dp-logo { width: 38px; height: 38px; border: 1px solid #27272a; border-radius: 12px; background: #18181b; color: #34d399; }
        .dp-panel { border: 1px solid #27272a; border-radius: 20px; background: rgba(24, 24, 27, .58); }
        .dp-stage { min-height: 380px; border: 1px solid #27272a; border-radius: 28px; background-color: rgba(24, 24, 27, .45); background-image: radial-gradient(#27272a 1px, transparent 1px); background-size: 16px 16px; }
        .dp-stage-name { font-size: clamp(2.3rem, 6vw, 5rem); line-height: 1.05; overflow-wrap: anywhere; }
        .fw-black { font-weight: 900; }
        .dp-badge { border: 1px solid #27272a; border-radius: 999px; background: #18181b; color: #a1a1aa; letter-spacing: .12em; }
        .dp-dot { width: 8px; height: 8px; border-radius: 999px; background: #10b981; box-shadow: 0 0 16px #10b981; }
        .dp-primary { background: #10b981; border-color: #10b981; color: #052e22; font-weight: 800; }
        .dp-primary:hover { background: #34d399; border-color: #34d399; color: #052e22; }
        .dp-list { max-height: 340px; overflow-y: auto; }
        .dp-list-item { border: 1px solid #27272a; border-radius: 12px; background: #09090b; }
        .dp-muted { color: #71717a; }
    </style>
</head>
<body>
    <header class="dp-header sticky-top px-3 px-md-4 py-3">
        <div class="dp-shell d-flex align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <div class="dp-logo d-flex align-items-center justify-content-center flex-shrink-0"><i class="ti ti-sparkles"></i></div>
                <div class="min-w-0">
                    <div class="fw-bold text-truncate">{{ $rapat?->judul ?? 'Undian Doorprize' }}</div>
                    <div class="small dp-muted">Undian peserta hadir · hasil tersimpan ke database</div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('doorprize.index') }}" class="btn btn-dark btn-sm"><i class="ti ti-gift me-1"></i>Master Hadiah</a>
                <button type="button" class="btn btn-dark btn-sm" onclick="toggleDoorprizeFullscreen()" title="Layar penuh">
                    <i class="ti ti-maximize"></i><span class="d-none d-sm-inline ms-1">Layar Penuh</span>
                </button>
            </div>
        </div>
    </header>

    <main class="dp-shell px-3 px-md-4 py-4 py-md-5">
        <livewire:doorprize-undian />
    </main>

    <script src="{{ asset('vendor/tabler/tabler.min.js') }}"></script>
    <script>
        function toggleDoorprizeFullscreen() {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen();
            }
        }
    </script>
</body>
</html>
