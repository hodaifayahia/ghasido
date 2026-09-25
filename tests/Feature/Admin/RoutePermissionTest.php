<?php

namespace Tests\Feature\Admin;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every admin route is guarded server side (spec 0001, AC-1, AC-2, AC-9).
 *
 * These turn the role capability table in AGENTS.md from documentation into
 * something executable: a refusal is a 403, by direct URL, never a redirect
 * and never a 404.
 */
class RoutePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * The seven guarded admin routes, and whether a manager may open each.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function managerVisibility(): array
    {
        return [
            'hotels' => ['hotels', true],
            'departments' => ['departments', true],
            'employees' => ['employees', true],
            'lessons & content' => ['lessons-content', true],
            'ai scenarios' => ['ai-scenarios', false],
            'messages & reminders' => ['messages-reminders', true],
            'reports & export' => ['reports-export', false],
        ];
    }

    /**
     * The seven guarded admin routes.
     *
     * @return array<string, array{0: string}>
     */
    public static function adminRoutes(): array
    {
        return array_map(
            static fn (array $row): array => [$row[0]],
            self::managerVisibility(),
        );
    }

    #[DataProvider('adminRoutes')]
    public function test_a_super_admin_reaches_every_admin_route(string $route)
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route($route))
            ->assertOk();
    }

    #[DataProvider('managerVisibility')]
    public function test_a_manager_reaches_only_their_own_admin_routes(
        string $route,
        bool $managerMayOpen,
    ) {
        $response = $this->actingAs(User::factory()->manager()->create())
            ->get(route($route));

        $managerMayOpen
            ? $response->assertOk()
            : $response->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_an_employee_is_refused_every_admin_route(string $route)
    {
        $this->actingAs(User::factory()->employee()->create())
            ->get(route($route))
            ->assertForbidden();
    }

    #[DataProvider('adminRoutes')]
    public function test_a_user_with_no_role_is_refused_every_admin_route(string $route)
    {
        $this->actingAs(User::factory()->create())
            ->get(route($route))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login_rather_than_refused()
    {
        // Not signed in is a different answer from not allowed.
        $this->get(route('hotels'))->assertRedirect(route('login'));
    }

    /**
     * The one behaviour the package documentation does not state outright.
     *
     * PermissionMiddleware calls canAny(), which routes through the Gate, so
     * the Gate::before override reaches the route boundary. Stripping the
     * role's own permission rows proves the 200 comes from the override and
     * not from a seeded row (AC-2).
     */
    public function test_the_super_admin_override_reaches_the_permission_middleware()
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Role::findByName(RoleEnum::SuperAdmin->value)->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertEmpty($superAdmin->fresh()?->permissionNames());

        $this->actingAs($superAdmin)
            ->get(route('hotels'))
            ->assertOk();
    }

    /**
     * A refusal renders the branded 403 view, not Laravel's default (AC-8).
     */
    public function test_a_refusal_renders_the_branded_error_page()
    {
        $response = $this->actingAs(User::factory()->employee()->create())
            ->get(route('hotels'));

        $response->assertForbidden();
        $response->assertSee('Access denied');
        $response->assertSee('Error 403');
        $response->assertSee('/brand/ghasido-logo.png', false);
    }
}
