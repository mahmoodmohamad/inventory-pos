<?php

use App\Exceptions\MissingTenantException;
use App\Exceptions\TenantViolationException;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->shopA = Shop::factory()->create();
    $this->shopB = Shop::factory()->create();
    $this->tenant = app(TenantContext::class);
});

it('reads only the current shop records', function () {
    Category::factory()->for($this->shopA)->count(2)->create();
    Category::factory()->for($this->shopB)->count(3)->create();

    expect($this->tenant->run($this->shopA, fn () => Category::count()))->toBe(2)
        ->and($this->tenant->run($this->shopB, fn () => Category::count()))->toBe(3);
});

it('fails closed when there is no tenant context', function () {
    Category::query()->count();
})->throws(MissingTenantException::class);

it('cannot find a record of another shop by its known id', function () {
    $catB = Category::factory()->for($this->shopB)->create();

    $this->tenant->run($this->shopA, function () use ($catB) {
        expect(Category::find($catB->id))->toBeNull();
        expect(fn () => Category::findOrFail($catB->id))->toThrow(ModelNotFoundException::class);
    });
});

it('sets shop_id automatically on create', function () {
    $category = $this->tenant->run($this->shopA, fn () => Category::create(['name' => 'Filters']));

    expect($category->shop_id)->toBe($this->shopA->id);
});

it('ignores a shop_id sent through mass assignment', function () {
    $category = $this->tenant->run($this->shopA, fn () => Category::create([
        'name' => 'Filters',
        'shop_id' => $this->shopB->id,
    ]));

    expect($category->shop_id)->toBe($this->shopA->id);
});

it('blocks creating a record explicitly for another shop', function () {
    $this->tenant->run($this->shopA, function () {
        $c = new Category(['name' => 'x']);
        $c->shop_id = $this->shopB->id;
        $c->save();
    });
})->throws(TenantViolationException::class);

it('blocks changing shop_id of an existing record', function () {
    $category = Category::factory()->for($this->shopA)->create();

    $this->tenant->run($this->shopA, function () use ($category) {
        $category->shop_id = $this->shopB->id;
        $category->save();
    });
})->throws(TenantViolationException::class);

it('blocks writing to another shop record even with scopes disabled', function () {
    $catB = Category::factory()->for($this->shopB)->create();

    $this->tenant->run($this->shopA, function () use ($catB) {
        $found = Category::withoutGlobalScopes()->find($catB->id);

        expect($found)->not->toBeNull(); // الـ scope اتعطّل فعلًا
        expect(fn () => $found->update(['name' => 'hacked']))->toThrow(TenantViolationException::class);
        expect(fn () => $found->delete())->toThrow(TenantViolationException::class);
    });
});

it('rejects at database level a parent from another shop', function () {
    $parentB = Category::factory()->for($this->shopB)->create();

    // إدخال مباشر بيتخطى كل طبقات الكود: لازم الـ composite FK يرفضه
    expect(fn () => DB::table('categories')->insert([
        'shop_id' => $this->shopA->id,
        'parent_id' => $parentB->id,
        'name' => 'bad child',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('sets the tenant context from the authenticated user', function () {
    Route::middleware(['web', 'auth'])->get('/_probe', fn () => (string) app(TenantContext::class)->id());

    $userA = User::factory()->for($this->shopA)->create();
    $userB = User::factory()->for($this->shopB)->create();

    $this->actingAs($userA)->get('/_probe')->assertOk()->assertSee((string) $this->shopA->id);
    $this->actingAs($userB)->get('/_probe')->assertOk()->assertSee((string) $this->shopB->id);
});
it('registers TenantContext as scoped, not a plain singleton', function () {
    $first = app(TenantContext::class);
    $first->set($this->shopA);

    app()->forgetScopedInstances();

    expect(app(TenantContext::class)->hasShop())->toBeFalse();
});
