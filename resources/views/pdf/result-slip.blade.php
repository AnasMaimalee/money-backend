<h2>CBT Result Slip</h2>

<p><strong>Name:</strong> {{ $exam->user->name }}</p>
<p><strong>Exam ID:</strong> {{ $exam->id }}</p>
<p><strong>Total Score:</strong> {{ $exam->total_score }}</p>
<p><strong>Time Used:</strong> {{ gmdate('H:i:s', $exam->time_used_seconds) }}</p>

<table width="100%" border="1" cellspacing="0" cellpadding="6">
    <thead>
    <tr>
        <th>Subject</th>
        <th>Total</th>
        <th>Correct</th>
        <th>Wrong</th>
        <th>Score</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($breakdown['subjects'] as $row)
        <tr>
            <td>{{ $row['subject'] }}</td>
            <td>{{ $row['total_questions'] }}</td>
            <td>{{ $row['correct'] }}</td>
            <td>{{ $row['wrong'] }}</td>
            <td>{{ $row['score'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
