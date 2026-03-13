import { router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { DocumentField } from '@/types/esign';

type FieldValueEntry = string | boolean | File | Blob | null;

export function useSigningSession() {
    const verified = ref(false);
    const fieldValues = ref<Map<string, FieldValueEntry>>(new Map());
    const submitting = ref(false);
    const declining = ref(false);
    const error = ref<string | null>(null);

    const filledCount = computed(() => {
        let count = 0;
        for (const value of fieldValues.value.values()) {
            if (isValueFilled(value)) {
                count++;
            }
        }
        return count;
    });

    const totalRequired = ref(0);

    const progress = computed(() => {
        if (totalRequired.value === 0) return 100;
        return Math.round((filledCount.value / totalRequired.value) * 100);
    });

    const isComplete = computed(() => filledCount.value >= totalRequired.value);

    function isValueFilled(value: FieldValueEntry): boolean {
        if (value === null || value === undefined) return false;
        if (typeof value === 'string' && value.trim() === '') return false;
        if (typeof value === 'boolean') return value;
        return true;
    }

    function setVerified(isVerified: boolean): void {
        verified.value = isVerified;
    }

    function setFieldValue(fieldId: string, value: FieldValueEntry): void {
        fieldValues.value.set(fieldId, value);
    }

    function getFieldValue(fieldId: string): FieldValueEntry | undefined {
        return fieldValues.value.get(fieldId);
    }

    function initializeRequiredFields(fields: DocumentField[]): void {
        totalRequired.value = fields.filter((f) => f.is_required).length;
    }

    function buildFormData(
        recipientId: string,
        fields: DocumentField[],
        values: Map<string, FieldValueEntry>,
    ): FormData {
        const formData = new FormData();
        formData.append('recipient_id', recipientId);

        for (const field of fields) {
            const value = values.get(field.id);
            if (value === null || value === undefined) continue;

            if (value instanceof File || value instanceof Blob) {
                formData.append(`fields[${field.id}][file]`, value);
            } else if (typeof value === 'boolean') {
                formData.append(`fields[${field.id}][value]`, value ? '1' : '0');
            } else {
                formData.append(`fields[${field.id}][value]`, String(value));
            }
        }

        return formData;
    }

    function submitSigning(
        recipientId: string,
        fields: DocumentField[],
        values: Map<string, FieldValueEntry>,
    ): Promise<void> {
        return new Promise((resolve, reject) => {
            submitting.value = true;
            error.value = null;

            const formData = buildFormData(recipientId, fields, values);

            // Extract document_id from the first field
            const documentId = fields[0]?.document_id;
            if (!documentId) {
                error.value = 'No document ID found';
                submitting.value = false;
                reject(new Error('No document ID found'));
                return;
            }

            router.post(`/esign/signing/${documentId}/submit`, formData, {
                forceFormData: true,
                onSuccess: () => {
                    submitting.value = false;
                    resolve();
                },
                onError: (errors) => {
                    submitting.value = false;
                    const firstError = Object.values(errors)[0];
                    error.value = typeof firstError === 'string' ? firstError : 'Failed to submit signing';
                    reject(new Error(error.value));
                },
                onFinish: () => {
                    submitting.value = false;
                },
            });
        });
    }

    function declineSigning(recipientId: string, reason: string): Promise<void> {
        return new Promise((resolve, reject) => {
            declining.value = true;
            error.value = null;

            router.post(
                `/esign/signing/${recipientId}/decline`,
                { reason },
                {
                    onSuccess: () => {
                        declining.value = false;
                        resolve();
                    },
                    onError: (errors) => {
                        declining.value = false;
                        const firstError = Object.values(errors)[0];
                        error.value = typeof firstError === 'string' ? firstError : 'Failed to decline signing';
                        reject(new Error(error.value));
                    },
                    onFinish: () => {
                        declining.value = false;
                    },
                },
            );
        });
    }

    function reset(): void {
        verified.value = false;
        fieldValues.value.clear();
        totalRequired.value = 0;
        submitting.value = false;
        declining.value = false;
        error.value = null;
    }

    return {
        verified,
        fieldValues,
        submitting,
        declining,
        error,
        filledCount,
        totalRequired,
        progress,
        isComplete,
        setVerified,
        setFieldValue,
        getFieldValue,
        initializeRequiredFields,
        submitSigning,
        declineSigning,
        reset,
    };
}
