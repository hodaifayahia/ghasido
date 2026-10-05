# Guesvia Project Overview

This repository contains the Guesvia web application, a hotel-staff English training platform built with Laravel, Inertia, Vue 3, and Tailwind CSS.

## Project purpose

Guesvia is designed to help hotel employees improve workplace English through role-based learning flows, structured lessons, assessments, and AI-supported scenarios. The platform is also intended to support research data collection and reporting.

## Core stack

- Laravel 13
- Inertia.js v3
- Vue 3 with `<script setup lang="ts">`
- Tailwind CSS v4
- Wayfinder routes
- PHP + Blade + Vue component structure

## Main domains

- Admin dashboard and management screens
- Hotel and department-based organization
- Employee account and progression tracking
- Lesson and content management
- Pre-test and post-test flows
- AI role-play and scenario support
- Reporting and export workflows

## Key project folders

- `system/` — product requirements and project documentation
- `desgin/` — design system and mockup-driven styling guidance
- `desginphotos/` — approved mockup images used as visual reference
- `resources/js/` — Vue pages, components, layouts, and frontend logic
- `resources/css/` — app styling and theme tokens
- `routes/` — Laravel route definitions
- `app/` — Laravel application logic
- `storage/harness/` — visual verification harness and screenshot tooling

## Development notes

The project follows the repository rules in `AGENTS.md`, which defines strict UI fidelity requirements and the approved design specification. Any frontend work should align with the mockups and design tokens rather than introducing independent styling.

## Typical workflow

1. Install dependencies with Composer and npm.
2. Run the Laravel app locally.
3. Build frontend assets with Vite.
4. Validate with project checks and type checks.
5. Compare UI against mockups before considering work complete.

## Recommended validation

Before finalizing UI changes, verify:

- app runs successfully
- interface matches the mockup at the reference size
- layout remains stable at smaller viewport sizes
- `npm run check` passes
- `npm run types:check` passes

## Notes

This repository appears to be a design-driven, product-focused SaaS application rather than a generic starter project. The project documentation is intentionally detailed, with emphasis on design fidelity, business logic, and role-based access controls.
