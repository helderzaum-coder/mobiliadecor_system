<x-filament-panels::page>
    {{-- Filtros inline --}}
    <div style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap;margin-bottom:16px;">

        {{-- Filtrar por --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Filtrar por</label>
            <select wire:model.live="filtro_filtrar_por"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                <option value="data_vencimento">📅 Vencimento</option>
                <option value="data_recebimento">💰 Recebimento</option>
                <option value="data_venda">🛒 Venda</option>
            </select>
        </div>

        {{-- Período --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Período</label>
            <select wire:model.live="filtro_periodo"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                <option value="hoje">Hoje</option>
                <option value="dia_especifico">Dia específico</option>
                <option value="esta_semana">Esta semana</option>
                <option value="este_mes">Este mês</option>
                <option value="mes_passado">Mês passado</option>
                <option value="selecionar_mes">Selecionar mês</option>
                <option value="customizado">Customizado</option>
            </select>
        </div>

        {{-- Dia específico --}}
        @if($filtro_periodo === 'dia_especifico')
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Dia</label>
            <input type="date" wire:model.live="filtro_dia"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
        </div>
        @endif

        {{-- Selecionar mês --}}
        @if($filtro_periodo === 'selecionar_mes')
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Mês</label>
            <select wire:model.live="filtro_mes"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                @foreach($this->getMesOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Customizado --}}
        @if($filtro_periodo === 'customizado')
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">De</label>
            <input type="date" wire:model.live="filtro_inicio"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Até</label>
            <input type="date" wire:model.live="filtro_fim"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
        </div>
        @endif

        {{-- Status --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Status</label>
            <select wire:model.live="filtro_status"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                <option value="">Todos</option>
                <option value="pendente">Pendente</option>
                <option value="recebido">Recebido</option>
                <option value="ajuste">Ajuste</option>
                <option value="cancelado">Cancelado</option>
            </select>
        </div>

        {{-- Canal --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Canal</label>
            <select wire:model.live="filtro_canal"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                <option value="">Todos</option>
                @foreach($this->getCanaisOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Conta --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Conta</label>
            <select wire:model.live="filtro_conta"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                <option value="">Todas</option>
                <option value="primary">Mobilia Decor</option>
                <option value="secondary">HES Móveis</option>
            </select>
        </div>

        {{-- Banco --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Banco</label>
            <select wire:model.live="filtro_banco"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;">
                <option value="">Todos</option>
                @foreach($this->getBancosOptions() as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </div>

        {{-- Lote --}}
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label style="font-size:11px;color:#9ca3af;">Nº Lote</label>
            <input type="number" wire:model.live.debounce.500ms="filtro_lote" placeholder="Ex: 42"
                style="padding:8px 10px;border-radius:8px;border:1px solid #374151;background:#111827;color:#fff;font-size:12px;width:90px;">
        </div>

        {{-- Limpar --}}
        @if($filtro_periodo !== 'este_mes' || $filtro_status || $filtro_canal || $filtro_conta || $filtro_banco || $filtro_lote || $filtro_filtrar_por !== 'data_vencimento')
        <div style="display:flex;flex-direction:column;gap:4px;justify-content:flex-end;">
            <button wire:click="$set('filtro_periodo','este_mes');$set('filtro_filtrar_por','data_vencimento');$set('filtro_status','');$set('filtro_canal','');$set('filtro_conta','');$set('filtro_banco','');$set('filtro_lote','');$set('filtro_dia','');$set('filtro_inicio','');$set('filtro_fim','')"
                style="padding:8px 12px;border-radius:8px;border:none;background:#374151;color:#9ca3af;font-size:12px;cursor:pointer;">
                ✕ Limpar
            </button>
        </div>
        @endif

    </div>

    {{ $this->table }}
</x-filament-panels::page>
