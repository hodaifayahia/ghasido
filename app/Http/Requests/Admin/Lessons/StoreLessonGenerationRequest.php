<?php

namespace App\Http\Requests\Admin\Lessons;

use App\Enums\ContentGenerationType;
use App\Enums\Role;
use App\Models\Course;
use App\Services\Content\LessonGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * "Generate with AI" on Lessons & Content (GEN-01, CMS-01, CMS-04, ORG-04;
 * spec 0004): one prompt, a department, a level, one lesson or a whole
 * course, an optional target course, and whether to make images and audio.
 *
 * Authorization: the user may create courses, and the hotel scope (or the
 * target course) is within their reach (ROLE-02, SEC-01).
 */
class StoreLessonGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('create', Course::class)) {
            return false;
        }

        $courseId = $this->input('course_id');
        if ($courseId !== null && $courseId !== '') {
            $course = Course::query()->find((int) $courseId);

            return $course === null || $user->can('update', $course);
        }

        $hotelId = $this->input('hotel_id');

        return $hotelId === null
            || $hotelId === ''
            || $user->hasRole(Role::SuperAdmin->value)
            || (int) $hotelId === $user->hotel_id;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:5', 'max:4000'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'hotel_id' => ['nullable', 'integer', Rule::exists('hotels', 'id')],
            'level' => ['required', 'string', Rule::in(LessonGenerator::LEVELS)],
            'mode' => ['required', 'string', Rule::in([ContentGenerationType::Lesson->value, ContentGenerationType::Course->value])],
            'lesson_count' => ['nullable', 'integer', 'min:1', 'max:'.LessonGenerator::MAX_LESSONS],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'images' => ['sometimes', 'boolean'],
            'audio' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A target course must belong to the chosen department, so the lessons
     * reach the learners the admin picked (JOURNEY-03).
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $courseId = $this->input('course_id');

                if ($courseId === null || $courseId === '' || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $course = Course::query()->find((int) $courseId);

                if ($course !== null && $course->department_id !== (int) $this->input('department_id')) {
                    $validator->errors()->add('course_id', __('Choose a course of the selected department.'));
                }
            },
        ];
    }

    /**
     * @return array{prompt: string, department_id: int, hotel_id: int|null, level: string, mode: string, lesson_count: int, course_id: int|null, images: bool, audio: bool}
     */
    public function generationData(): array
    {
        $hotel = $this->validated('hotel_id');
        $course = $this->validated('course_id');

        return [
            'prompt' => (string) $this->validated('prompt'),
            'department_id' => (int) $this->validated('department_id'),
            'hotel_id' => $hotel === null || $hotel === '' ? null : (int) $hotel,
            'level' => (string) $this->validated('level'),
            'mode' => (string) $this->validated('mode'),
            'lesson_count' => (int) ($this->validated('lesson_count') ?? 1),
            'course_id' => $course === null || $course === '' ? null : (int) $course,
            'images' => $this->boolean('images'),
            'audio' => $this->boolean('audio'),
        ];
    }
}
