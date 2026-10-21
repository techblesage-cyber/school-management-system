<?php

namespace App\Enums;

/**
 * Things a staff member can be allowed to do inside their school's portal.
 * Which roles get which abilities is decided in StaffRole::abilities().
 */
enum SchoolAbility: string
{
    case ViewStudents = 'view_students';
    case ManageStudents = 'manage_students';
    case DeleteStudents = 'delete_students';
    case ManageClasses = 'manage_classes';
    case ViewAdmissions = 'view_admissions';
    case ManageAdmissions = 'manage_admissions';
    case DecideAdmissions = 'decide_admissions';
    case ManageStaff = 'manage_staff';
    case EditSchoolProfile = 'edit_school_profile';
}
