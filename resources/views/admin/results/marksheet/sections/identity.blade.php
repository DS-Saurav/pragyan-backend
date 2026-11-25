<div class="identity">
    <div>
        <span class="pair"><span class="label">Student's Name:</span> <span class="val">{{ $row['name'] }}</span></span>
        <span class="pair"><span class="label">Class:</span> <span class="val">{{ $class->name }}</span></span>
        <span class="pair"><span class="label">Section:</span> <span class="val">{{ $class->section ?: '-' }}</span></span>
    </div>
    <div>
        <span class="pair"><span class="label">Roll No.:</span> <span class="val">{{ $row['roll'] ?: '-' }}</span></span>
        @if ($layout->flag('identity', 'exam_name'))
            <span class="pair"><span class="label">Examination:</span> <span class="val">{{ $exam->name }}</span></span>
        @endif
        @if ($layout->flag('identity', 'academic_year'))
            <span class="pair"><span class="label">Academic Year:</span> <span class="val">{{ $exam->academic_year }}</span></span>
        @endif
    </div>
    <div>
        @if ($layout->flag('identity', 'dob'))
            <span class="pair">
                <span class="label">DOB (A.D.):</span> <span class="val">{{ $student->dob ?: '-' }}</span>
            </span>
        @endif
        @if ($layout->flag('identity', 'dob_bs'))
            <span class="pair">
                {{-- Stage P3H: the stored Bikram Sambat DOB, exactly as
                     recorded. There is no automatic A.D.<->B.S. conversion -
                     never a guessed or converted value. --}}
                <span class="label">DOB (B.S.):</span>
                <span class="val {{ $student->dob_bs ? '' : 'na' }}">{{ $student->dob_bs ?: '-' }}</span>
                @unless ($student->dob_bs)
                    <span style="font-size:8px;color:#64748b"> (not recorded)</span>
                @endunless
            </span>
        @endif
        @if ($layout->flag('identity', 'admission_no'))
            <span class="pair"><span class="label">Admission No.:</span> <span class="val">{{ $row['admission'] ?: '-' }}</span></span>
        @endif
    </div>
</div>
