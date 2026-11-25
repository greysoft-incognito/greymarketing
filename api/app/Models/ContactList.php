<?php

namespace App\Models;

use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'name',
        'description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list_contact')->withPivot('created_at');
    }

    /**
     * Add contacts by primary key, ignoring ones already on the list.
     *
     * @param  array<int, int>  $contactIds
     */
    public function addContacts(array $contactIds): void
    {
        $now = now();

        $rows = array_map(fn ($id) => [
            'contact_list_id' => $this->id,
            'contact_id' => $id,
            'created_at' => $now,
        ], $contactIds);

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->contacts()->newPivotStatement()->insertOrIgnore($chunk);
        }
    }
}
