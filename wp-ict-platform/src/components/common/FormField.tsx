/**
 * Form Field Component
 *
 * Accessible form field with validation, character counter, and help text
 *
 * @package ICT_Platform
 * @since   1.0.0
 */

import React, { useId, useState, useCallback } from 'react';
import { HelpTooltip } from './Tooltip';
import { Icon } from './Icon';

interface FormFieldProps {
  label: string;
  name: string;
  type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url' | 'date' | 'datetime-local' | 'time' | 'textarea' | 'select';
  value: string | number;
  onChange: (value: string | number) => void;
  onBlur?: () => void;
  error?: string;
  helperText?: string;
  helpTooltip?: string;
  required?: boolean;
  disabled?: boolean;
  readOnly?: boolean;
  placeholder?: string;
  maxLength?: number;
  minLength?: number;
  min?: number;
  max?: number;
  step?: number;
  rows?: number;
  options?: Array<{ value: string | number; label: string; disabled?: boolean }>;
  showCharacterCount?: boolean;
  autoComplete?: string;
  className?: string;
  inputClassName?: string;
  labelClassName?: string;
  'aria-describedby'?: string;
}

export const FormField: React.FC<FormFieldProps> = ({
  label,
  name,
  type = 'text',
  value,
  onChange,
  onBlur,
  error,
  helperText,
  helpTooltip,
  required = false,
  disabled = false,
  readOnly = false,
  placeholder,
  maxLength,
  minLength,
  min,
  max,
  step,
  rows = 3,
  options = [],
  showCharacterCount = false,
  autoComplete,
  className = '',
  inputClassName = '',
  labelClassName = '',
  'aria-describedby': ariaDescribedBy,
}) => {
  const id = useId();
  const errorId = `${id}-error`;
  const helperId = `${id}-helper`;
  const [isFocused, setIsFocused] = useState(false);

  const handleChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
      const newValue = type === 'number' ? parseFloat(e.target.value) || '' : e.target.value;
      onChange(newValue);
    },
    [onChange, type]
  );

  const handleFocus = () => setIsFocused(true);
  const handleBlur = () => {
    setIsFocused(false);
    onBlur?.();
  };

  const characterCount = typeof value === 'string' ? value.length : 0;
  const showCount = showCharacterCount && maxLength && type !== 'select';

  const describedByIds = [
    error ? errorId : null,
    helperText ? helperId : null,
    ariaDescribedBy,
  ]
    .filter(Boolean)
    .join(' ');

  const inputProps = {
    id,
    name,
    value,
    onChange: handleChange,
    onFocus: handleFocus,
    onBlur: handleBlur,
    disabled,
    readOnly,
    placeholder,
    maxLength,
    minLength,
    autoComplete,
    required,
    'aria-invalid': !!error,
    'aria-describedby': describedByIds || undefined,
    className: `ict-form-field__input ${error ? 'ict-form-field__input--error' : ''} ${inputClassName}`,
  };

  const renderInput = () => {
    if (type === 'textarea') {
      return <textarea {...inputProps} rows={rows} />;
    }

    if (type === 'select') {
      return (
        <select {...inputProps}>
          {placeholder && (
            <option value="" disabled>
              {placeholder}
            </option>
          )}
          {options.map((option) => (
            <option key={option.value} value={option.value} disabled={option.disabled}>
              {option.label}
            </option>
          ))}
        </select>
      );
    }

    return (
      <input
        {...inputProps}
        type={type}
        min={min}
        max={max}
        step={step}
      />
    );
  };

  return (
    <div
      className={`ict-form-field ${error ? 'ict-form-field--error' : ''} ${isFocused ? 'ict-form-field--focused' : ''} ${disabled ? 'ict-form-field--disabled' : ''} ${className}`}
    >
      <div className="ict-form-field__label-row">
        <label htmlFor={id} className={`ict-form-field__label ${labelClassName}`}>
          {label}
          {required && (
            <span className="ict-form-field__required" aria-hidden="true">
              *
            </span>
          )}
        </label>
        {helpTooltip && <HelpTooltip content={helpTooltip} />}
      </div>

      <div className="ict-form-field__input-wrapper">
        {renderInput()}
        {type === 'select' && (
          <span className="ict-form-field__select-arrow" aria-hidden="true">
            <Icon name="chevron-down" size={16} />
          </span>
        )}
      </div>

      <div className="ict-form-field__footer">
        <div className="ict-form-field__messages">
          {error && (
            <p id={errorId} className="ict-form-field__error" role="alert">
              <Icon name="alert-circle" size={14} aria-hidden="true" />
              {error}
            </p>
          )}
          {!error && helperText && (
            <p id={helperId} className="ict-form-field__helper">
              {helperText}
            </p>
          )}
        </div>
        {showCount && (
          <span
            className={`ict-form-field__counter ${characterCount === maxLength ? 'ict-form-field__counter--max' : ''}`}
            aria-live="polite"
          >
            {characterCount}/{maxLength}
          </span>
        )}
      </div>
    </div>
  );
};

// Currency input
interface CurrencyFieldProps extends Omit<FormFieldProps, 'type' | 'value' | 'onChange'> {
  value: number;
  onChange: (value: number) => void;
  currency?: string;
}

export const CurrencyField: React.FC<CurrencyFieldProps> = ({
  value,
  onChange,
  currency = '$',
  ...props
}) => {
  const handleChange = (newValue: string | number) => {
    const numValue = typeof newValue === 'string' ? parseFloat(newValue) || 0 : newValue;
    onChange(numValue);
  };

  return (
    <div className="ict-currency-field">
      <span className="ict-currency-field__symbol">{currency}</span>
      <FormField
        {...props}
        type="number"
        value={value}
        onChange={handleChange}
        min={0}
        step={0.01}
        inputClassName="ict-currency-field__input"
      />
    </div>
  );
};

// Form validation hook
interface ValidationRule {
  validate: (value: unknown) => boolean;
  message: string;
}

interface UseFormValidationOptions {
  fields: Record<string, ValidationRule[]>;
  validateOnChange?: boolean;
  validateOnBlur?: boolean;
}

export function useFormValidation<T extends Record<string, unknown>>({
  fields,
  validateOnChange = false,
  validateOnBlur = true,
}: UseFormValidationOptions) {
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [touched, setTouched] = useState<Record<string, boolean>>({});

  const validateField = useCallback(
    (name: string, value: unknown): string | undefined => {
      const rules = fields[name];
      if (!rules) return undefined;

      for (const rule of rules) {
        if (!rule.validate(value)) {
          return rule.message;
        }
      }
      return undefined;
    },
    [fields]
  );

  const validateAll = useCallback(
    (data: T): boolean => {
      const newErrors: Record<string, string> = {};
      let isValid = true;

      Object.keys(fields).forEach((name) => {
        const error = validateField(name, data[name]);
        if (error) {
          newErrors[name] = error;
          isValid = false;
        }
      });

      setErrors(newErrors);
      return isValid;
    },
    [fields, validateField]
  );

  const handleBlur = useCallback(
    (name: string, value: unknown) => {
      setTouched((prev) => ({ ...prev, [name]: true }));
      if (validateOnBlur) {
        const error = validateField(name, value);
        setErrors((prev) => ({
          ...prev,
          [name]: error || '',
        }));
      }
    },
    [validateOnBlur, validateField]
  );

  const handleChange = useCallback(
    (name: string, value: unknown) => {
      if (validateOnChange && touched[name]) {
        const error = validateField(name, value);
        setErrors((prev) => ({
          ...prev,
          [name]: error || '',
        }));
      }
    },
    [validateOnChange, touched, validateField]
  );

  const clearErrors = useCallback(() => {
    setErrors({});
    setTouched({});
  }, []);

  return {
    errors,
    touched,
    validateField,
    validateAll,
    handleBlur,
    handleChange,
    clearErrors,
    getFieldError: (name: string) => (touched[name] ? errors[name] : undefined),
  };
}

// Common validation rules
export const validationRules = {
  required: (message = 'This field is required'): ValidationRule => ({
    validate: (value) =>
      value !== undefined && value !== null && value !== '',
    message,
  }),
  email: (message = 'Please enter a valid email address'): ValidationRule => ({
    validate: (value) =>
      typeof value === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    message,
  }),
  minLength: (min: number, message?: string): ValidationRule => ({
    validate: (value) =>
      typeof value === 'string' && value.length >= min,
    message: message || `Must be at least ${min} characters`,
  }),
  maxLength: (max: number, message?: string): ValidationRule => ({
    validate: (value) =>
      typeof value === 'string' && value.length <= max,
    message: message || `Must be at most ${max} characters`,
  }),
  min: (min: number, message?: string): ValidationRule => ({
    validate: (value) =>
      typeof value === 'number' && value >= min,
    message: message || `Must be at least ${min}`,
  }),
  max: (max: number, message?: string): ValidationRule => ({
    validate: (value) =>
      typeof value === 'number' && value <= max,
    message: message || `Must be at most ${max}`,
  }),
  pattern: (regex: RegExp, message: string): ValidationRule => ({
    validate: (value) =>
      typeof value === 'string' && regex.test(value),
    message,
  }),
};

export default FormField;
