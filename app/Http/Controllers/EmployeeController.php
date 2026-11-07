<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public const PAYMENT_TYPES = ['salary' => 'Salaire', 'advance' => 'Avance', 'bonus' => 'Prime', 'deduction' => 'Retenue'];

    public function index(Request $request): View
    {
        $period = now()->format('Y-m');

        return view('employees.index', [
            'employees' => Employee::with('user:id,name')
                ->when($request->query('statut', 'active') !== 'tous', fn ($q) => $q->where('status', $request->query('statut', 'active')))
                ->withSum(['salaryPayments as paid_this_month' => fn ($q) => $q->where('period', $period)->whereIn('type', ['salary', 'advance', 'bonus'])], 'amount')
                ->orderBy('first_name')->get(),
            'payroll' => (float) Employee::where('status', 'active')->sum('salary'),
            'period' => $period,
        ]);
    }

    public function create(): View
    {
        return view('employees.form', [
            'employee' => new Employee(['hire_date' => today(), 'status' => 'active', 'salary_frequency' => 'monthly']),
            'users' => $this->availableUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $next = (int) Employee::withTrashed()->max('id') + 1;
        $employee = Employee::create($data + ['employee_number' => 'EMP-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT)]);

        return redirect()->route('employees.show', $employee)->with('success', 'Employé ajouté.');
    }

    public function show(Employee $employee): View
    {
        $period = request('periode', now()->format('Y-m'));
        $payments = $employee->salaryPayments()->with('user:id,name')->latest('payment_date')->latest('id')->paginate(20);
        $month = $employee->salaryPayments()->where('period', $period)->get();

        $advances = (float) $month->where('type', 'advance')->sum('amount');
        $deductions = (float) $month->where('type', 'deduction')->sum('amount');
        $paidSalary = (float) $month->where('type', 'salary')->sum('amount');

        return view('employees.show', [
            'employee' => $employee->load('user'),
            'payments' => $payments,
            'period' => $period,
            'summary' => [
                'salary' => (float) $employee->salary,
                'advances' => $advances,
                'deductions' => $deductions,
                'bonus' => (float) $month->where('type', 'bonus')->sum('amount'),
                'paid' => $paidSalary,
                'remaining' => max(0, round((float) $employee->salary - $advances - $deductions - $paidSalary, 2)),
            ],
            'types' => self::PAYMENT_TYPES,
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.form', ['employee' => $employee, 'users' => $this->availableUsers($employee)]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $employee->update($this->validated($request, $employee));

        return redirect()->route('employees.show', $employee)->with('success', 'Fiche mise à jour.');
    }

    public function pay(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::PAYMENT_TYPES))],
            'period' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,moncash,natcash,bank,cheque'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        SalaryPayment::create($data + ['employee_id' => $employee->id, 'user_id' => $request->user()->id]);

        return back()->with('success', self::PAYMENT_TYPES[$data['type']].' de '.number_format((float) $data['amount'], 2, ',', ' ').' HTG enregistré(e).');
    }

    public function destroyPayment(SalaryPayment $payment): RedirectResponse
    {
        abort_unless($payment->created_at->isToday(), 422, 'Seul un paiement du jour peut être supprimé.');
        $payment->delete();

        return back()->with('success', 'Paiement supprimé.');
    }

    private function availableUsers(?Employee $employee = null)
    {
        return User::whereDoesntHave('employee', fn ($q) => $q->when($employee, fn ($w) => $w->where('id', '!=', $employee->id)))
            ->orderBy('name')->get(['id', 'name', 'email']);
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($employee?->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:M,F'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'nif_cin' => ['nullable', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'salary' => ['required', 'numeric', 'min:0'],
            'salary_frequency' => ['required', 'in:monthly,biweekly,weekly'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,suspended,terminated'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
