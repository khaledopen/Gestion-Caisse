<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Transaction extends Model
{
    public const TYPES = ['recette' => 'Recette', 'depense' => 'Dépense', 'approvisionnement' => 'Approvisionnement', 'retrait' => 'Retrait'];
    public const METHODS = ['especes' => 'Espèces', 'mobile_money' => 'Mobile Money', 'virement' => 'Virement'];
    protected $guarded = [];
    protected function casts(): array { return ['amount_minor' => 'integer', 'occurred_on' => 'date', 'cancelled_at' => 'datetime']; }
    public function user() { return $this->belongsTo(User::class); }
    public function canceller() { return $this->belongsTo(User::class, 'cancelled_by'); }
    public function isInflow(): bool { return in_array($this->type, ['recette', 'approvisionnement'], true); }
    public function getReferenceAttribute(): string { return 'CA-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
}
