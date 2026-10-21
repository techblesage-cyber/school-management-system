<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\CircularAudience;
use App\Enums\StaffRole;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Circular;
use App\Models\FeeItem;
use App\Models\GradingScale;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Mark;
use App\Models\ParentAccount;
use App\Models\Payment;
use App\Models\ReportComment;
use App\Models\ResultPublication;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\Results\TermResults;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * A demo school to try every part of the system:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Staff sign in at /school/login (password: password123):
 *   head@demo.test, teacher@demo.test, bursar@demo.test, secretary@demo.test
 * Parents' portal at /parent: phone 0700 000 001, password parent123
 */
class DemoSeeder extends Seeder
{
    private const FIRST = ['Amina', 'Brian', 'Cathy', 'Daniel', 'Esther', 'Fred', 'Grace', 'Henry', 'Irene', 'Joseph', 'Juliet', 'Kenneth', 'Lydia', 'Moses', 'Naomi', 'Patrick', 'Rebecca', 'Samuel', 'Sharon', 'Timothy'];

    private const LAST = ['Nakato', 'Okello', 'Akello', 'Mugisha', 'Namubiru', 'Ssemakula', 'Atim', 'Kato', 'Nabirye', 'Opio', 'Tumusiime', 'Achieng', 'Wasswa', 'Nansubuga', 'Byaruhanga', 'Auma', 'Mukasa', 'Nakimera', 'Ochieng', 'Kyomuhendo'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('The demo school is for trying the system out, not for the live server.');

            return;
        }

        if (School::query()->where('slug', 'demo')->exists()) {
            $this->command?->warn('The demo school already exists. Delete it in the admin panel first to start again.');

            return;
        }

        mt_srand(2026);

        $school = School::create([
            'name' => 'Demo Secondary School',
            'slug' => 'demo',
            'motto' => 'Knowledge is power',
            'address' => 'P.O. Box 100, Jinja',
            'phone' => '0434 120 000',
            'email' => 'info@demo.test',
            'is_active' => true,
        ]);

        // Terms: last term finished, this term running, next term booked
        $term2 = AcademicTerm::create(['school_id' => $school->id, 'year' => 2026, 'term' => 2, 'starts_on' => '2026-05-25', 'ends_on' => '2026-08-21', 'is_current' => false]);
        $term3 = AcademicTerm::create(['school_id' => $school->id, 'year' => 2026, 'term' => 3, 'starts_on' => '2026-09-14', 'ends_on' => '2026-12-04', 'is_current' => true]);
        AcademicTerm::create(['school_id' => $school->id, 'year' => 2027, 'term' => 1, 'starts_on' => '2027-02-02', 'ends_on' => '2027-04-30', 'is_current' => false]);

        $scale = GradingScale::addStandard($school, 'O-level (A–E)');
        GradingScale::addStandard($school, 'Primary (D1–F9)');
        GradingScale::addStandard($school, 'A-level (A–F)');

        // Staff
        $staff = [];
        foreach ([
            'head' => ['Sarah Namutebi', StaffRole::HeadTeacher],
            'teacher' => ['Peter Ouma', StaffRole::Teacher],
            'bursar' => ['Joan Akiror', StaffRole::Bursar],
            'secretary' => ['Ruth Nambi', StaffRole::Secretary],
            'teacher2' => ['David Kiggundu', StaffRole::Teacher],
        ] as $key => [$name, $role]) {
            $staff[$key] = User::query()->firstOrCreate(['email' => "{$key}@demo.test"], ['name' => $name, 'password' => 'password123']);
            $school->users()->syncWithoutDetaching([$staff[$key]->id => ['role' => $role->value]]);
        }

        // Classes and streams
        $classes = [];
        foreach (['S.1' => 20, 'S.2' => 21] as $name => $order) {
            $classes[$name] = SchoolClass::create(['school_id' => $school->id, 'name' => $name, 'level' => 'o_level', 'sort_order' => $order, 'grading_scale_id' => $scale->id]);
            $classes[$name]->streams()->createMany([['name' => 'East'], ['name' => 'West']]);
        }
        $classes['S.1']->streams()->where('name', 'East')->update(['class_teacher_id' => $staff['teacher']->id]);
        $classes['S.2']->update(['class_teacher_id' => $staff['teacher2']->id]);

        $subjects = collect(['English', 'Mathematics', 'Biology', 'Chemistry', 'Physics', 'Geography', 'History & Political Education'])
            ->mapWithKeys(fn (string $name): array => [$name => Subject::create(['school_id' => $school->id, 'name' => $name])]);

        TeachingAssignment::syncFor($school, $staff['teacher'], [
            ['subject_id' => $subjects['Mathematics']->id, 'classes' => ['c:'.$classes['S.1']->id, 'c:'.$classes['S.2']->id]],
            ['subject_id' => $subjects['Physics']->id, 'classes' => ['c:'.$classes['S.1']->id]],
        ]);
        TeachingAssignment::syncFor($school, $staff['teacher2'], [
            ['subject_id' => $subjects['English']->id, 'classes' => ['c:'.$classes['S.1']->id, 'c:'.$classes['S.2']->id]],
            ['subject_id' => $subjects['Biology']->id, 'classes' => ['c:'.$classes['S.2']->id]],
        ]);

        // Students and parents. The first two share the demo parent.
        $demoParent = Guardian::create(['school_id' => $school->id, 'name' => 'Grace Nakato', 'phone' => '0700 000 001', 'occupation' => 'Nurse']);
        $students = collect();
        $index = 0;

        foreach ($classes as $class) {
            foreach ($class->streams as $stream) {
                foreach (range(1, 6) as $n) {
                    $first = self::FIRST[$index % 20];
                    $last = $index < 2 ? 'Nakato' : self::LAST[($index * 7) % 20];
                    $index++;

                    $student = Student::create([
                        'school_id' => $school->id,
                        'first_name' => $first,
                        'last_name' => $last,
                        'gender' => in_array($first, ['Amina', 'Cathy', 'Esther', 'Grace', 'Irene', 'Juliet', 'Lydia', 'Naomi', 'Rebecca', 'Sharon'], true) ? 'female' : 'male',
                        'date_of_birth' => CarbonImmutable::create(2013 - ($class->name === 'S.2' ? 1 : 0), mt_rand(1, 12), mt_rand(1, 28)),
                        'school_class_id' => $class->id,
                        'stream_id' => $stream->id,
                        'residence' => mt_rand(0, 2) === 0 ? 'boarding' : 'day',
                        'admission_date' => '2026-02-02',
                    ]);

                    $parent = $index <= 2
                        ? $demoParent
                        : Guardian::create(['school_id' => $school->id, 'name' => self::FIRST[($index + 5) % 20].' '.$last, 'phone' => '07'.mt_rand(10000000, 99999999)]);

                    $student->guardians()->attach($parent, ['relationship' => $index <= 2 ? 'mother' : 'guardian', 'is_primary' => true]);
                    $students->push($student);
                }
            }
        }

        [$account] = ParentAccount::grantTo($demoParent);
        $account->update(['password' => 'parent123']);

        // Exams and marks for last term and this term
        foreach ([$term2, $term3] as $term) {
            $mid = Assessment::create(['school_id' => $school->id, 'academic_term_id' => $term->id, 'name' => 'Mid-term', 'max_score' => 100, 'weight' => 40, 'sort_order' => 1]);
            $end = Assessment::create(['school_id' => $school->id, 'academic_term_id' => $term->id, 'name' => 'End of term', 'max_score' => 100, 'weight' => 60, 'sort_order' => 2]);

            foreach ($students as $student) {
                $ability = 45 + ($student->id * 13) % 40;

                foreach ($subjects as $subject) {
                    foreach ([$mid, $end] as $exam) {
                        Mark::create([
                            'school_id' => $school->id, 'assessment_id' => $exam->id, 'student_id' => $student->id, 'subject_id' => $subject->id,
                            'school_class_id' => $student->school_class_id, 'stream_id' => $student->stream_id,
                            'score' => max(10, min(99, $ability + mt_rand(-15, 15) + ($term === $term3 ? 3 : 0))),
                            'entered_by' => $staff['teacher']->id,
                        ]);
                    }
                }
            }

            // Comments and publishing: last term for everyone, this term for S.1
            foreach ($classes as $class) {
                foreach (TermResults::forClass($term, $class)->students as $result) {
                    ReportComment::create([
                        'school_id' => $school->id, 'student_id' => $result->student->id, 'academic_term_id' => $term->id,
                        'class_teacher_comment' => $result->suggestedComment(),
                        'head_teacher_comment' => $result->average >= 70 ? 'Well done. Keep it up.' : 'Work harder next term.',
                    ]);
                }

                if ($term === $term2 || $class->name === 'S.1') {
                    ResultPublication::create(['school_id' => $school->id, 'academic_term_id' => $term->id, 'school_class_id' => $class->id, 'published_by' => $staff['head']->id]);
                }
            }
        }

        // Fees for this term, with some payments
        foreach ([['Tuition', 850_000, null], ['Development fee', 100_000, null], ['Boarding', 650_000, 'boarding']] as [$name, $amount, $residence]) {
            FeeItem::create(['school_id' => $school->id, 'academic_term_id' => $term3->id, 'name' => $name, 'amount' => $amount, 'residence' => $residence]);
        }
        Invoice::billTerm($term3);

        foreach ($students as $i => $student) {
            if ($i % 4 !== 3) {
                Payment::create([
                    'school_id' => $school->id, 'student_id' => $student->id, 'academic_term_id' => $term3->id,
                    'amount' => [950_000, 500_000, 300_000][$i % 3], 'method' => ['cash', 'mtn_momo', 'bank'][$i % 3],
                    'reference' => $i % 3 === 0 ? null : 'REF'.(10_000 + $i), 'paid_on' => '2026-09-'.str_pad((string) (15 + $i % 10), 2, '0', STR_PAD_LEFT),
                    'received_by' => $staff['bursar']->id,
                ]);
            }
        }

        // Two weeks of attendance for S.1 East
        $east = $classes['S.1']->streams()->where('name', 'East')->first();
        foreach (range(1, 14) as $daysAgo) {
            $day = today()->subDays($daysAgo);

            if ($day->isWeekend()) {
                continue;
            }

            foreach ($students->where('stream_id', $east->id) as $student) {
                AttendanceRecord::record($student, $day->toDateString(), mt_rand(1, 12) === 1 ? AttendanceStatus::Absent : AttendanceStatus::Present, by: $staff['teacher']);
            }
        }

        // Circulars
        Circular::create(['school_id' => $school->id, 'title' => 'Visiting day', 'body' => '<p>Dear parents, visiting day is on <strong>Sunday 1st November</strong> from 9am to 4pm.</p><p>Please carry your child\'s report card.</p>', 'audience' => CircularAudience::Parents, 'published_at' => now()->subDays(2), 'created_by' => $staff['secretary']->id]);
        Circular::create(['school_id' => $school->id, 'title' => 'Staff meeting on Friday', 'body' => '<p>All staff meet in the library at 4pm to discuss end of term exams.</p>', 'audience' => CircularAudience::Staff, 'published_at' => now()->subDay(), 'created_by' => $staff['head']->id]);

        $this->command?->info('Demo school ready at /school/demo');
        $this->command?->line('Staff (password "password123"): head@demo.test, teacher@demo.test, bursar@demo.test, secretary@demo.test');
        $this->command?->line('Parents\' portal at /parent: phone 0700 000 001, password "parent123"');
    }
}
