<?php
// C:\xampp\htdocs\qacrmtaktik\models\maquinasModel.php
/**
 * Máquinas — TAKTIK (PROD)
 * - Catálogo
 * - DataTables server-side
 * - Borrado lógico (activo=0)
 * - calendario_id: VALIDAR multi-tenant (empresa_id) + activo + eliminado_en
 * - NO usar métodos save()/update() que choquen con Eloquent Model
 */

use Illuminate\Database\Capsule\Manager as Capsule;

class maquinasModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // =========================
    // Helpers
    // =========================
    private function i($v): int { return (int)($v ?? 0); }
    private function s($v): string { return trim((string)($v ?? '')); }
    private function now(): string { return date('Y-m-d H:i:s'); }

    private function nullIfEmpty($v)
    {
        $v = $this->s($v);
        return $v === '' ? null : $v;
    }

    private function empresaOk(int $empresaId): void
    {
        if ($empresaId <= 0) throw new Exception('Empresa inválida');
    }

    // =========================
    // DataTables
    // =========================
    public function dtList(int $empresaId, array $q): array
    {
        $this->empresaOk($empresaId);

        $draw   = $this->i($q['draw'] ?? 1);
        $start  = max(0, $this->i($q['start'] ?? 0));
        $length = $this->i($q['length'] ?? 25);
        if ($length <= 0 || $length > 200) $length = 25;

        $search  = $this->s(($q['search']['value'] ?? ''));

        // filtros UX
        $fActivo = $this->s($q['f_activo'] ?? ''); // '', '1', '0'
        $fTipo   = $this->s($q['f_tipo'] ?? '');   // '', '...'

        // Total (sin filtros UX, solo empresa)
        $total = (int) Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->count();

        // Query base
        $base = Capsule::table('maquinas as m')
            ->leftJoin('calendarios_laborales as c', function($j){
                $j->on('c.id', '=', 'm.calendario_id')
                  ->on('c.empresa_id', '=', 'm.empresa_id')
                  ->where('c.activo', '=', 1)
                  ->whereNull('c.eliminado_en');
            })
            ->where('m.empresa_id', $empresaId)
            ->select([
                'm.id',
                'm.codigo',
                'm.nombre',
                'm.tipo',
                'm.activo',
                'm.calendario_id',
                Capsule::raw('COALESCE(c.nombre,"") as calendario_nombre'),
                Capsule::raw('DATE_FORMAT(m.creado_en,"%Y-%m-%d %H:%i:%s") as creado_en'),
                Capsule::raw('DATE_FORMAT(m.actualizado_en,"%Y-%m-%d %H:%i:%s") as actualizado_en'),
                // Si proceso_maquina trae empresa_id, cámbialo por:
                // Capsule::raw('(SELECT COUNT(*) FROM proceso_maquina pm WHERE pm.empresa_id=m.empresa_id AND pm.maquina_id=m.id) as procesos_count'),
                Capsule::raw('(SELECT COUNT(*) FROM proceso_maquina pm WHERE pm.maquina_id = m.id) as procesos_count'),
            ]);

        if ($fActivo !== '' && in_array($fActivo, ['0','1'], true)) {
            $base->where('m.activo', (int)$fActivo);
        }

        if ($fTipo !== '') {
            $base->where('m.tipo', $fTipo);
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $base->where(function($w) use ($like){
                $w->where('m.nombre','like',$like)
                  ->orWhere('m.codigo','like',$like)
                  ->orWhere('m.tipo','like',$like)
                  ->orWhere(Capsule::raw('COALESCE(c.nombre,"")'),'like',$like);
            });
        }

        // filtered count (antes de paginar)
        $filtered = (int) (clone $base)->count();

        // orden DT
        $cols = [
            0 => 'm.id',
            1 => 'm.codigo',
            2 => 'm.nombre',
            3 => 'm.tipo',
            4 => 'calendario_nombre',
            5 => 'm.activo',
            6 => 'procesos_count',
            7 => 'm.creado_en',
        ];

        $orderCol = $this->i($q['order'][0]['column'] ?? 7);
        $orderDir = strtolower($this->s($q['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $orderBy  = $cols[$orderCol] ?? 'm.creado_en';

        if ($orderBy === 'procesos_count') {
            $base->orderByRaw('procesos_count '.$orderDir);
        } elseif ($orderBy === 'calendario_nombre') {
            $base->orderByRaw('calendario_nombre '.$orderDir);
        } else {
            $base->orderBy($orderBy, $orderDir);
        }

        // paginado
        $rows = $base->offset($start)->limit($length)->get()
            ->map(fn($r)=>(array)$r)->toArray();

        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows
        ];
    }

    // =========================
    // Catálogos auxiliares
    // =========================
    public function calendariosList(int $empresaId): array
    {
        $this->empresaOk($empresaId);

        return Capsule::table('calendarios_laborales')
            ->where('empresa_id', $empresaId)
            ->where('activo', 1)
            ->whereNull('eliminado_en')
            ->select('id','nombre')
            ->orderBy('nombre','asc')
            ->get()->map(fn($r)=>(array)$r)->toArray();
    }

    public function tiposList(int $empresaId): array
    {
        $this->empresaOk($empresaId);

        return Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->whereNotNull('tipo')
            ->where('tipo','<>','')
            ->select('tipo')
            ->distinct()
            ->orderBy('tipo','asc')
            ->pluck('tipo')
            ->toArray();
    }

    // =========================
    // CRUD
    // =========================
    public function getMaq(int $empresaId, int $id): ?array
    {
        $this->empresaOk($empresaId);

        $r = Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        return $r ? (array)$r : null;
    }

    public function saveMaq(int $empresaId, array $in): int
    {
        $this->empresaOk($empresaId);

        $id          = $this->i($in['id'] ?? 0);
        $codigo      = $this->nullIfEmpty($in['codigo'] ?? null);
        $nombre      = $this->s($in['nombre'] ?? '');
        $tipo        = $this->nullIfEmpty($in['tipo'] ?? null);
        $activo      = $this->s($in['activo'] ?? '1');
        $calendario  = $this->i($in['calendario_id'] ?? 0);

        if ($nombre === '') throw new Exception('Nombre requerido');

        $activo = ($activo === '0' || $activo === 'false') ? 0 : 1;
        $calendarioId = $calendario > 0 ? $calendario : null;

        // unique por empresa (nombre)
        $dupNombre = Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->where('nombre', $nombre);
        if ($id > 0) $dupNombre->where('id','<>',$id);
        if ($dupNombre->exists()) throw new Exception('Ya existe una máquina con ese nombre');

        // unique por empresa (codigo) SOLO si codigo no es null/empty
        if ($codigo !== null) {
            $dupCodigo = Capsule::table('maquinas')
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo);
            if ($id > 0) $dupCodigo->where('id','<>',$id);
            if ($dupCodigo->exists()) throw new Exception('Ya existe una máquina con ese código');
        }

        // valida calendario si viene
        if ($calendarioId !== null) {
            $okCal = Capsule::table('calendarios_laborales')
                ->where('id', $calendarioId)
                ->where('empresa_id', $empresaId)
                ->where('activo', 1)
                ->whereNull('eliminado_en')
                ->exists();
            if (!$okCal) throw new Exception('Calendario inválido');
        }

        $data = [
            'empresa_id' => $empresaId,
            'calendario_id' => $calendarioId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'activo' => $activo,
            'actualizado_en' => $this->now(),
        ];

        if ($id > 0) {
            Capsule::table('maquinas')
                ->where('empresa_id', $empresaId)
                ->where('id', $id)
                ->update($data);
            return $id;
        }

        $data['creado_en'] = $this->now();
        return (int) Capsule::table('maquinas')->insertGetId($data);
    }

    public function deleteMaq(int $empresaId, int $id): void
    {
        $this->empresaOk($empresaId);

        $updated = Capsule::table('maquinas')
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->update([
                'activo' => 0,
                'actualizado_en' => $this->now(),
            ]);

        if (!$updated) throw new Exception('No se pudo desactivar');
    }
}
