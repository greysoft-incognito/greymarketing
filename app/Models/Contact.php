<?php

namespace App\Models;

use App\Enums\Channel;
use App\Traits\HasPublicUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\URL;

class Contact extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'email',
        'phone',
        'first_name',
        'last_name',
        'fields',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'email_unsubscribed_at' => 'datetime',
            'sms_unsubscribed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_contact')->withPivot('created_at');
    }

    /**
     * Contacts that can receive messages on the channel: reachable and not opted out.
     */
    public function scopeSubscribedTo(Builder $query, Channel $channel): void
    {
        $query->whereNotNull($channel === Channel::EMAIL ? 'email' : 'phone')
            ->whereNull("{$channel->value}_unsubscribed_at");
    }

    public function isSubscribedTo(Channel $channel): bool
    {
        $address = $channel === Channel::EMAIL ? $this->email : $this->phone;

        return $address !== null && $this->{"{$channel->value}_unsubscribed_at"} === null;
    }

    public function unsubscribe(Channel $channel): void
    {
        $column = "{$channel->value}_unsubscribed_at";

        $this->forceFill([$column => $this->{$column} ?? now()])->save();
    }

    public function resubscribe(Channel $channel): void
    {
        $this->forceFill(["{$channel->value}_unsubscribed_at" => null])->save();
    }

    /**
     * Signed link a recipient follows to opt out of a channel.
     */
    public function unsubscribeUrl(Channel $channel = Channel::EMAIL): string
    {
        return URL::signedRoute('unsubscribe', ['contact' => $this->ulid, 'channel' => $channel->value]);
    }

    /**
     * Values available to template merge tags.
     *
     * @return array<string, mixed>
     */
    public function mergeData(): array
    {
        return [
            ...($this->fields ?? []),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
