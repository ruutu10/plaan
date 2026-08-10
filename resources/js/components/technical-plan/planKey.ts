import { inject } from 'vue';
import type { InjectionKey, Ref } from 'vue';
import type { Plan, WizardConfig } from '@/types/technicalPlan';

/**
 * Injection key for the shared, reactive wizard plan. The `TechnicalPlan` page
 * owns the reactive object and provides it; step components inject and mutate
 * it directly (a shared store, not a one-way prop).
 */
export const planKey: InjectionKey<Plan> = Symbol('technical-plan');

/**
 * Resolve the shared wizard plan. Must be called inside the `TechnicalPlan`
 * provider tree.
 */
export function usePlan(): Plan {
    const plan = inject(planKey);

    if (!plan) {
        throw new Error(
            'usePlan() must be used within the TechnicalPlan provider.',
        );
    }

    return plan;
}

/**
 * Injection key for whether the wizard is pointing out what a step still owes.
 * Flipped on only once a step has been left with something missing, so a
 * performer is never told off about a field they have not reached yet.
 */
export const showValidationKey: InjectionKey<Ref<boolean>> = Symbol(
    'technical-plan-validation',
);

/**
 * Resolve whether validation messages are being shown. Must be called inside
 * the `TechnicalPlan` provider tree.
 */
export function useShowValidation(): Ref<boolean> {
    const showValidation = inject(showValidationKey);

    if (!showValidation) {
        throw new Error(
            'useShowValidation() must be used within the TechnicalPlan provider.',
        );
    }

    return showValidation;
}

/**
 * Injection key for the static wizard configuration (upload limits, tech
 * contact, …) provided by the `TechnicalPlan` page.
 */
export const configKey: InjectionKey<WizardConfig> = Symbol(
    'technical-plan-config',
);

/**
 * Resolve the shared wizard configuration. Must be called inside the
 * `TechnicalPlan` provider tree.
 */
export function useWizardConfig(): WizardConfig {
    const config = inject(configKey);

    if (!config) {
        throw new Error(
            'useWizardConfig() must be used within the TechnicalPlan provider.',
        );
    }

    return config;
}
