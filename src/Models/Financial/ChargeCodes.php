<?php

declare(strict_types=1);

// File: guinchafacil/src/Models/Financial/ChargeCodes.php
// ROADMAP socorro automotivo — Etapa 11 (financeiro de duas fases).
// Códigos estáveis definidos pelo usuário em 22/07/2026. Propositalmente
// VARCHAR na tabela (não ENUM) — validados aqui, na aplicação, e não no
// schema, porque são "eventos e decisões financeiras diferentes" (cotação
// literal do usuário) e um ENUM gigante "viraria um pequeno cartório dentro
// do banco". Qualquer novo código deve ser adicionado aqui primeiro.

final class ChargeCodes
{
    // --- phase_code ---
    public const PHASE_INITIAL_ASSISTANCE = 'INITIAL_ASSISTANCE';
    public const PHASE_ON_SITE_DIAGNOSIS = 'ON_SITE_DIAGNOSIS';
    public const PHASE_ON_SITE_SERVICE = 'ON_SITE_SERVICE';
    public const PHASE_PARTS_SUPPLY = 'PARTS_SUPPLY';
    public const PHASE_TOWING = 'TOWING';
    public const PHASE_CANCELLATION = 'CANCELLATION';
    public const PHASE_NO_SHOW = 'NO_SHOW';
    public const PHASE_CONVERSION = 'CONVERSION';

    public const PHASES = [
        self::PHASE_INITIAL_ASSISTANCE,
        self::PHASE_ON_SITE_DIAGNOSIS,
        self::PHASE_ON_SITE_SERVICE,
        self::PHASE_PARTS_SUPPLY,
        self::PHASE_TOWING,
        self::PHASE_CANCELLATION,
        self::PHASE_NO_SHOW,
        self::PHASE_CONVERSION,
    ];

    // --- charge_type ---
    public const TYPE_DISPATCH_FEE = 'DISPATCH_FEE';
    public const TYPE_TRAVEL_FEE = 'TRAVEL_FEE';
    public const TYPE_ATTENDANCE_FEE = 'ATTENDANCE_FEE';
    public const TYPE_DIAGNOSIS_FEE = 'DIAGNOSIS_FEE';
    public const TYPE_LABOR_FEE = 'LABOR_FEE';
    public const TYPE_WAITING_FEE = 'WAITING_FEE';
    public const TYPE_PARTS_FEE = 'PARTS_FEE';
    public const TYPE_TOWING_BASE_FEE = 'TOWING_BASE_FEE';
    public const TYPE_TOWING_DISTANCE_FEE = 'TOWING_DISTANCE_FEE';
    public const TYPE_TOLL_FEE = 'TOLL_FEE';
    public const TYPE_NIGHT_SURCHARGE = 'NIGHT_SURCHARGE';
    public const TYPE_HOLIDAY_SURCHARGE = 'HOLIDAY_SURCHARGE';
    public const TYPE_CONVERSION_BONUS = 'CONVERSION_BONUS';
    public const TYPE_CANCELLATION_FEE = 'CANCELLATION_FEE';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';
    public const TYPE_REFUND = 'REFUND';

    public const CHARGE_TYPES = [
        self::TYPE_DISPATCH_FEE, self::TYPE_TRAVEL_FEE, self::TYPE_ATTENDANCE_FEE,
        self::TYPE_DIAGNOSIS_FEE, self::TYPE_LABOR_FEE, self::TYPE_WAITING_FEE,
        self::TYPE_PARTS_FEE, self::TYPE_TOWING_BASE_FEE, self::TYPE_TOWING_DISTANCE_FEE,
        self::TYPE_TOLL_FEE, self::TYPE_NIGHT_SURCHARGE, self::TYPE_HOLIDAY_SURCHARGE,
        self::TYPE_CONVERSION_BONUS, self::TYPE_CANCELLATION_FEE, self::TYPE_ADJUSTMENT,
        self::TYPE_REFUND,
    ];

    // --- charge_status --- (dimensão: o que aconteceu com a cobrança em si)
    public const CHARGE_PENDING = 'PENDING';
    public const CHARGE_CALCULATED = 'CALCULATED';
    public const CHARGE_AWAITING_CUSTOMER_APPROVAL = 'AWAITING_CUSTOMER_APPROVAL';
    public const CHARGE_APPROVED = 'APPROVED';
    public const CHARGE_CANCELLED = 'CANCELLED';
    public const CHARGE_DISPUTED = 'DISPUTED';
    public const CHARGE_REJECTED = 'REJECTED';
    public const CHARGE_REFUNDED = 'REFUNDED';

    public const CHARGE_STATUSES = [
        self::CHARGE_PENDING, self::CHARGE_CALCULATED, self::CHARGE_AWAITING_CUSTOMER_APPROVAL,
        self::CHARGE_APPROVED, self::CHARGE_CANCELLED, self::CHARGE_DISPUTED,
        self::CHARGE_REJECTED, self::CHARGE_REFUNDED,
    ];

    // --- payable_status --- (dimensão: o que acontece com o repasse ao prestador)
    public const PAYABLE_NOT_ELIGIBLE = 'NOT_ELIGIBLE';
    public const PAYABLE_PENDING_EVIDENCE = 'PENDING_EVIDENCE';
    public const PAYABLE_PENDING_REVIEW = 'PENDING_REVIEW';
    public const PAYABLE_ELIGIBLE = 'ELIGIBLE';
    public const PAYABLE_BLOCKED = 'BLOCKED';
    public const PAYABLE_SCHEDULED = 'SCHEDULED';
    public const PAYABLE_PAID = 'PAID';
    public const PAYABLE_REVERSED = 'REVERSED';

    public const PAYABLE_STATUSES = [
        self::PAYABLE_NOT_ELIGIBLE, self::PAYABLE_PENDING_EVIDENCE, self::PAYABLE_PENDING_REVIEW,
        self::PAYABLE_ELIGIBLE, self::PAYABLE_BLOCKED, self::PAYABLE_SCHEDULED,
        self::PAYABLE_PAID, self::PAYABLE_REVERSED,
    ];

    // --- situação do moto-socorro (governa ChargePolicyService) ---
    public const SITUATION_RESOLVED_ON_SITE = 'RESOLVED_ON_SITE';
    public const SITUATION_TOWING_RECOMMENDED_ACCEPTED = 'TOWING_RECOMMENDED_ACCEPTED';
    public const SITUATION_CANCELLED_DURING_SERVICE = 'CANCELLED_DURING_SERVICE';
    public const SITUATION_TOWING_RECOMMENDED_REFUSED = 'TOWING_RECOMMENDED_REFUSED';
    public const SITUATION_NO_SHOW = 'NO_SHOW';
    public const SITUATION_MISSING_REQUIRED_EVIDENCE = 'MISSING_REQUIRED_EVIDENCE';
    public const SITUATION_INCONSISTENT_DIAGNOSIS = 'INCONSISTENT_DIAGNOSIS_OR_SUSPECTED_CONVERSION';
    public const SITUATION_CONFIRMED_FRAUD = 'CONFIRMED_FRAUDULENT_CONVERSION';
    public const SITUATION_CUSTOMER_ABSENT_AFTER_ARRIVAL = 'CUSTOMER_ABSENT_AFTER_ARRIVAL';
    public const SITUATION_SAFETY_RISK_INTERRUPTION = 'SAFETY_RISK_INTERRUPTION';
    public const SITUATION_PLATFORM_FAILURE_AFTER_ARRIVAL = 'PLATFORM_FAILURE_AFTER_ARRIVAL';
    public const SITUATION_OTHER_PROVIDER_EXECUTES_TOWING = 'OTHER_PROVIDER_EXECUTES_TOWING';

    public const SITUATIONS = [
        self::SITUATION_RESOLVED_ON_SITE,
        self::SITUATION_TOWING_RECOMMENDED_ACCEPTED,
        self::SITUATION_CANCELLED_DURING_SERVICE,
        self::SITUATION_TOWING_RECOMMENDED_REFUSED,
        self::SITUATION_NO_SHOW,
        self::SITUATION_MISSING_REQUIRED_EVIDENCE,
        self::SITUATION_INCONSISTENT_DIAGNOSIS,
        self::SITUATION_CONFIRMED_FRAUD,
        self::SITUATION_CUSTOMER_ABSENT_AFTER_ARRIVAL,
        self::SITUATION_SAFETY_RISK_INTERRUPTION,
        self::SITUATION_PLATFORM_FAILURE_AFTER_ARRIVAL,
        self::SITUATION_OTHER_PROVIDER_EXECUTES_TOWING,
    ];
}
