<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->catA = Category::factory()->for($this->shopA)->create();
    $this->catB = Category::factory()->for($this->shopB)->create();
});

function userWithRole(Shop $shop, UserRole $role, array $attrs = []): User
{
    return User::factory()->for($shop)->create(['role' => $role, ...$attrs]);
}

it('denies another shop record as 404 for every role', function (UserRole $role) {
    $user = userWithRole($this->shopA, $role);

    foreach (['view', 'update', 'delete'] as $ability) {
        $response = Gate::forUser($user)->inspect($ability, $this->catB);

        expect($response->denied())->toBeTrue()
            ->and($response->status())->toBe(404);
    }
})->with([UserRole::Owner, UserRole::Manager, UserRole::Cashier]);

it('lets every role view a record of their own shop', function (UserRole $role) {
    $user = userWithRole($this->shopA, $role);

    expect(Gate::forUser($user)->allows('view', $this->catA))->toBeTrue();
})->with([UserRole::Owner, UserRole::Manager, UserRole::Cashier]);

it('lets owner and manager manage, but not cashier', function () {
    $owner = userWithRole($this->shopA, UserRole::Owner);
    $manager = userWithRole($this->shopA, UserRole::Manager);
    $cashier = userWithRole($this->shopA, UserRole::Cashier);

    foreach ([$owner, $manager] as $user) {
        expect(Gate::forUser($user)->allows('create', Category::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $this->catA))->toBeTrue();
    }

    $response = Gate::forUser($cashier)->inspect('update', $this->catA);

    expect(Gate::forUser($cashier)->allows('create', Category::class))->toBeFalse()
        ->and($response->denied())->toBeTrue()
        ->and($response->status())->not->toBe(404);
});

it('denies an inactive user even with a manager role', function () {
    $user = userWithRole($this->shopA, UserRole::Manager, ['is_active' => false]);

    expect(Gate::forUser($user)->allows('create', Category::class))->toBeFalse();
});
it('builds a composite tenant foreign key through the macro', function () {
    $connection = DB::connection();
    $connection->getSchemaBuilder(); // بيهيّئ الـ schema grammar

    $blueprint = new Blueprint(
        $connection,
        'probe_items',
        function (Blueprint $table) {
            $table->foreignId('shop_id');
            $table->tenantForeign('category_id', nullable: true);
        },
    );

    $sql = implode(' ', $blueprint->toSql());

    expect($sql)->toContain('foreign key (`shop_id`, `category_id`) references `categories` (`shop_id`, `id`)')
        ->and($sql)->not->toContain('cascade');
});
