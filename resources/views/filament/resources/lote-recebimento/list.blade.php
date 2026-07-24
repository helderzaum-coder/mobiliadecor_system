<x-filament-panels::page>
    {{-- Filtros inline --}}
    <div style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:16px;">

        {{-- Busca --}}
        <div style="display:flex;flex-direction:column;gap:4px;min-width:160px;">
            <label style="font-size:11px;color:#9ca3af;">Descrição</label>
            <input type="text" wire:model.live.debounce.400ms="busca" placeholder="Buscar lote..."
                style="padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
        </div>

        {{-- Período --}}
        <div style="display:flex;flex-direction:column;gap:4px;min-width:180px;">
            <label style="font-size:11px;color:#9ca3af;">Período</label>
            <select wire:model.live="periodo"
                style="padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
                <option value="hoje">Hoje</option>
                <option value="dia_especifico">Dia específico</option>
                <option value="esta_semana">Esta semana</option>
                <option value="este_mes">Este mês</option>
                <option value="mes_passado">Mês passado</option>
                <option value="selecionar_mes">Selecionar mês</option>
                <option value="customizado">Período customizado</option>
            </select>
        </div>

        {{-- Dia específico --}}
        @if($periodo === 'dia_especifico')
        <div style="display:flex;flex-direction:column;gap:4px;min-width:150px;">
            <label style="font-size:11px;color:#9ca3af;">Dia</label>
            <input type="date" wire:model.live="dia_especifico"
                style="padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
        </div>
        @endif

        {{-- Selecionar mês --}}
        @if($periodo === 'selecionar_mes')
        <div style="display:flex;flex-direction:column;gap:4px;min-width:200px;">
            <label style="font-size:11px;color:#9ca3af;">Mês</label>
            <select wire:model.live="mes_selecionado"
                style="padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
                @foreach($this->getMesOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Período customizado --}}
        @if($periodo === 'customizado')
        <div style="display:flex;flex-direction:column;gap:4px;min-width:150px;">
            <label style="font-size:11px;color:#9ca3af;">De</label>
            <input type="date" wire:model.live="data_inicio"
                style="padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;min-width:150px;">
            <label style="font-size:11px;color:#9ca3af;">Até</label>
            <input type="date" wire:model.live="data_fim"
                style="padding:8px 12px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:13px;">
        </div>
        @endif

    </div>

    {{ $this->table }}
</x-filament-panels::page>
