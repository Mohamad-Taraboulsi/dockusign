import { ref } from 'vue';
import type { DocumentField, FieldType } from '@/types/esign';

export type FieldError = {
    fieldId: string;
    message: string;
};

type FieldValue = string | boolean | File | Blob | null | undefined;

const FILE_BASED_TYPES: FieldType[] = ['signature', 'initials', 'stamp'];
const TEXT_TYPES: FieldType[] = ['text_name', 'text_title', 'text_email', 'note'];

export function useFieldValidation() {
    const fieldErrors = ref<Map<string, string>>(new Map());

    function validateField(field: DocumentField, value: FieldValue): string | null {
        // Required check
        if (field.is_required && isEmpty(value)) {
            return `${field.label || field.type} is required`;
        }

        // Skip further validation if empty and not required
        if (isEmpty(value)) {
            return null;
        }

        // Type-specific validation
        if (FILE_BASED_TYPES.includes(field.type)) {
            return validateFileField(field, value);
        }

        if (TEXT_TYPES.includes(field.type)) {
            return validateTextField(field, value);
        }

        if (field.type === 'checkbox') {
            return validateCheckbox(field, value);
        }

        if (field.type === 'dropdown' || field.type === 'radio') {
            return validateSelection(field, value);
        }

        if (field.type === 'text_email') {
            return validateEmail(value);
        }

        if (field.type === 'date_signed') {
            return validateDate(value);
        }

        if (field.type === 'attachment') {
            return validateAttachment(value);
        }

        return null;
    }

    function isEmpty(value: FieldValue): boolean {
        if (value === null || value === undefined) return true;
        if (typeof value === 'string' && value.trim() === '') return true;
        if (typeof value === 'boolean') return false;
        return false;
    }

    function validateFileField(field: DocumentField, value: FieldValue): string | null {
        // Value should be a File/Blob or a string URL/data-URI pointing to the image
        if (value instanceof File || value instanceof Blob) {
            return null;
        }
        if (typeof value === 'string' && value.length > 0) {
            // Accept data URIs and URLs
            if (value.startsWith('data:image/') || value.startsWith('http') || value.startsWith('/')) {
                return null;
            }
            return `${field.label || field.type} must be a valid image file or drawn signature`;
        }
        if (field.is_required) {
            return `${field.label || field.type} requires a file or drawn image`;
        }
        return null;
    }

    function validateTextField(field: DocumentField, value: FieldValue): string | null {
        if (typeof value !== 'string') {
            return `${field.label || field.type} must be text`;
        }

        const rules = field.validation_rules;
        if (rules) {
            const minLength = rules.minLength as number | undefined;
            const maxLength = rules.maxLength as number | undefined;
            const pattern = rules.pattern as string | undefined;

            if (minLength !== undefined && value.length < minLength) {
                return `${field.label || field.type} must be at least ${minLength} characters`;
            }
            if (maxLength !== undefined && value.length > maxLength) {
                return `${field.label || field.type} must be at most ${maxLength} characters`;
            }
            if (pattern) {
                const regex = new RegExp(pattern);
                if (!regex.test(value)) {
                    return `${field.label || field.type} format is invalid`;
                }
            }
        }

        // Built-in email validation for text_email type
        if (field.type === 'text_email') {
            return validateEmail(value);
        }

        return null;
    }

    function validateCheckbox(field: DocumentField, value: FieldValue): string | null {
        if (typeof value !== 'boolean') {
            return `${field.label || field.type} must be checked or unchecked`;
        }
        if (field.is_required && !value) {
            return `${field.label || field.type} must be checked`;
        }
        return null;
    }

    function validateSelection(field: DocumentField, value: FieldValue): string | null {
        if (typeof value !== 'string' || value.trim() === '') {
            return `${field.label || field.type} requires a selection`;
        }

        // Validate against allowed options if provided
        const allowedOptions = field.options?.items as string[] | undefined;
        if (allowedOptions && Array.isArray(allowedOptions) && !allowedOptions.includes(value)) {
            return `${field.label || field.type} has an invalid selection`;
        }

        return null;
    }

    function validateEmail(value: FieldValue): string | null {
        if (typeof value !== 'string') return 'Email must be text';
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            return 'Please enter a valid email address';
        }
        return null;
    }

    function validateDate(value: FieldValue): string | null {
        if (typeof value !== 'string') return 'Date must be text';
        const date = new Date(value);
        if (isNaN(date.getTime())) {
            return 'Please enter a valid date';
        }
        return null;
    }

    function validateAttachment(value: FieldValue): string | null {
        if (value instanceof File || value instanceof Blob) {
            return null;
        }
        if (typeof value === 'string' && value.length > 0) {
            return null;
        }
        return 'Attachment requires a file';
    }

    function validateAllFields(
        fields: DocumentField[],
        values: Map<string, FieldValue>,
    ): FieldError[] {
        const errors: FieldError[] = [];
        fieldErrors.value.clear();

        for (const field of fields) {
            const value = values.get(field.id);
            const errorMessage = validateField(field, value);
            if (errorMessage) {
                errors.push({ fieldId: field.id, message: errorMessage });
                fieldErrors.value.set(field.id, errorMessage);
            }
        }

        return errors;
    }

    function getFieldErrors(): Map<string, string> {
        return fieldErrors.value;
    }

    function clearErrors(): void {
        fieldErrors.value.clear();
    }

    function getFieldError(fieldId: string): string | null {
        return fieldErrors.value.get(fieldId) ?? null;
    }

    return {
        fieldErrors,
        validateField,
        validateAllFields,
        getFieldErrors,
        getFieldError,
        clearErrors,
    };
}
