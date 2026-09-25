export type RolePermissionOption = {
    name: string;
    label: string;
};

export type RolePermissionGroup = {
    group: string;
    permissions: RolePermissionOption[];
};

export type RoleRecord = {
    id: number;
    /** The stored role name, e.g. `super_admin` or a custom `Content Auditor`. */
    name: string;
    /** A human label for display. */
    label: string;
    /** One of the four fixed roles: renaming and deleting are blocked. */
    isSystem: boolean;
    /** The Super Admin role: its permission set is fixed to everything. */
    locked: boolean;
    /** How many accounts currently hold this role. */
    userCount: number;
    /** The permission names this role holds. */
    permissions: string[];
};
