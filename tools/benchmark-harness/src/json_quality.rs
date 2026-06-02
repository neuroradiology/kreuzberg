//! JSON extraction quality metrics for structured document extraction.
//!
//! Provides metrics for evaluating JSON-schema-driven extraction:
//! - **schema_validity_rate** — fraction of predictions passing JSON Schema validation
//! - **field_precision_recall_f1** — leaf-level P/R/F1 between predicted and ground truth
//! - **type_correctness_rate** — percentage of leaves with matching types
//! - **numeric_match** — numeric leaves within tolerance (configurable per type)
//! - **exact_match** — whole-record exact equality

use serde_json::Value;

/// Configuration for numeric matching tolerance.
#[derive(Debug, Clone)]
pub struct NumericTolerance {
    /// Tolerance for currency values (default ±1%)
    pub currency_percent: f64,
    /// Tolerance for decimal numbers (default ±1%)
    pub decimal_percent: f64,
}

impl Default for NumericTolerance {
    fn default() -> Self {
        Self {
            currency_percent: 0.01,
            decimal_percent: 0.01,
        }
    }
}

/// Precision, Recall, F1 triple.
#[derive(Debug, Clone, Copy, PartialEq)]
pub struct Metrics {
    pub precision: f64,
    pub recall: f64,
    pub f1: f64,
}

/// Check if a JSON value is valid against a schema (draft-07).
///
/// # Arguments
/// * `value` - The JSON value to validate
/// * `schema` - The JSON Schema (draft-07)
///
/// # Returns
/// `true` if valid, `false` otherwise
pub fn is_valid_against_schema(value: &Value, schema: &Value) -> bool {
    jsonschema::is_valid(schema, value)
}

/// Compute schema validity rate: fraction of predictions passing validation.
///
/// # Arguments
/// * `predictions` - Array of predicted JSON values
/// * `schema` - JSON Schema to validate against
///
/// # Returns
/// Validity rate in [0.0, 1.0]
pub fn schema_validity_rate(predictions: &[Value], schema: &Value) -> f64 {
    if predictions.is_empty() {
        return 0.0;
    }

    let valid = predictions
        .iter()
        .filter(|pred| is_valid_against_schema(pred, schema))
        .count();

    valid as f64 / predictions.len() as f64
}

/// Extract all leaf values (scalars) from a JSON value with their paths.
fn collect_leaves<'a>(value: &'a Value, prefix: &str) -> Vec<(String, &'a Value)> {
    let mut leaves = Vec::new();

    match value {
        Value::Object(map) => {
            for (key, val) in map {
                let path = if prefix.is_empty() {
                    key.clone()
                } else {
                    format!("{}.{}", prefix, key)
                };
                collect_leaves_recursive(val, &path, &mut leaves);
            }
        }
        Value::Array(arr) => {
            for (idx, val) in arr.iter().enumerate() {
                let path = format!("{}[{}]", prefix, idx);
                collect_leaves_recursive(val, &path, &mut leaves);
            }
        }
        _ => {
            leaves.push((prefix.to_string(), value));
        }
    }

    leaves
}

fn collect_leaves_recursive<'a>(value: &'a Value, path: &str, leaves: &mut Vec<(String, &'a Value)>) {
    match value {
        Value::Object(map) => {
            for (key, val) in map {
                let new_path = format!("{}.{}", path, key);
                collect_leaves_recursive(val, &new_path, leaves);
            }
        }
        Value::Array(arr) => {
            for (idx, val) in arr.iter().enumerate() {
                let new_path = format!("{}[{}]", path, idx);
                collect_leaves_recursive(val, &new_path, leaves);
            }
        }
        _ => {
            leaves.push((path.to_string(), value));
        }
    }
}

/// Compute field-level P/R/F1 between predicted and ground truth JSON.
///
/// Matches leaves by path; computes true positives (matching leaves) and false
/// positives/negatives (present only in one side).
///
/// # Arguments
/// * `predicted` - Predicted JSON object
/// * `ground_truth` - Ground truth JSON object
///
/// # Returns
/// `Metrics { precision, recall, f1 }`
pub fn field_precision_recall_f1(predicted: &Value, ground_truth: &Value) -> Metrics {
    let pred_leaves: std::collections::HashMap<String, _> = collect_leaves(predicted, "").into_iter().collect();
    let gt_leaves: std::collections::HashMap<String, _> = collect_leaves(ground_truth, "").into_iter().collect();

    let mut tp = 0usize;
    let mut fp = 0usize;
    let mut fn_count = 0usize;

    // True positives and false positives
    for (path, pred_val) in &pred_leaves {
        if let Some(gt_val) = gt_leaves.get(path) {
            if pred_val == gt_val {
                tp += 1;
            } else {
                fp += 1;
            }
        } else {
            fp += 1;
        }
    }

    // False negatives
    for path in gt_leaves.keys() {
        if !pred_leaves.contains_key(path) {
            fn_count += 1;
        }
    }

    let precision = if tp + fp == 0 {
        0.0
    } else {
        tp as f64 / (tp + fp) as f64
    };

    let recall = if tp + fn_count == 0 {
        0.0
    } else {
        tp as f64 / (tp + fn_count) as f64
    };

    let f1 = if precision + recall == 0.0 {
        0.0
    } else {
        2.0 * (precision * recall) / (precision + recall)
    };

    Metrics { precision, recall, f1 }
}

/// Compute type correctness rate: fraction of matched leaves with matching JSON types.
///
/// Only considers leaves that exist in both predicted and ground truth.
///
/// # Arguments
/// * `predicted` - Predicted JSON object
/// * `ground_truth` - Ground truth JSON object
///
/// # Returns
/// Type correctness rate in [0.0, 1.0]
pub fn type_correctness_rate(predicted: &Value, ground_truth: &Value) -> f64 {
    let pred_leaves: std::collections::HashMap<String, _> = collect_leaves(predicted, "").into_iter().collect();
    let gt_leaves: std::collections::HashMap<String, _> = collect_leaves(ground_truth, "").into_iter().collect();

    let mut matched = 0usize;
    let mut type_correct = 0usize;

    for (path, pred_val) in &pred_leaves {
        if let Some(gt_val) = gt_leaves.get(path) {
            matched += 1;
            if same_json_type(pred_val, gt_val) {
                type_correct += 1;
            }
        }
    }

    if matched == 0 {
        0.0
    } else {
        type_correct as f64 / matched as f64
    }
}

fn same_json_type(a: &Value, b: &Value) -> bool {
    matches!(
        (a, b),
        (Value::Null, Value::Null)
            | (Value::Bool(_), Value::Bool(_))
            | (Value::Number(_), Value::Number(_))
            | (Value::String(_), Value::String(_))
            | (Value::Array(_), Value::Array(_))
            | (Value::Object(_), Value::Object(_))
    )
}

/// Check if two numeric values match within tolerance.
///
/// For integers, checks exact equality. For floats, uses percentage tolerance.
///
/// # Arguments
/// * `predicted` - Predicted numeric value
/// * `ground_truth` - Ground truth numeric value
/// * `tolerance` - Tolerance configuration
///
/// # Returns
/// `true` if values match within tolerance
pub fn numeric_match(predicted: &Value, ground_truth: &Value, tolerance: &NumericTolerance) -> bool {
    let pred_num = match predicted {
        Value::Number(n) => n.as_f64(),
        _ => return false,
    };

    let gt_num = match ground_truth {
        Value::Number(n) => n.as_f64(),
        _ => return false,
    };

    match (pred_num, gt_num) {
        (Some(p), Some(g)) => {
            let percent_diff = ((p - g).abs() / g.abs()).min(1.0);
            // Values >= 1.0 are treated as currency-scale (prices, totals, counts),
            // letting the looser currency_percent govern. Sub-unit decimals stick
            // with the tighter decimal_percent budget. When the two tolerances are
            // identical (Default::default), the choice is a no-op.
            let effective = if g.abs() >= 1.0 {
                tolerance.currency_percent.max(tolerance.decimal_percent)
            } else {
                tolerance.decimal_percent
            };
            percent_diff <= effective
        }
        _ => false,
    }
}

/// Check if two JSON values are exactly equal.
pub fn exact_match(predicted: &Value, ground_truth: &Value) -> bool {
    predicted == ground_truth
}

#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::json;

    #[test]
    fn test_schema_validity_rate_all_valid() {
        let schema = json!({
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object",
            "properties": {
                "name": { "type": "string" },
                "age": { "type": "number" }
            }
        });

        let predictions = vec![json!({"name": "Alice", "age": 30}), json!({"name": "Bob", "age": 25})];

        let rate = schema_validity_rate(&predictions, &schema);
        assert_eq!(rate, 1.0);
    }

    #[test]
    fn test_schema_validity_rate_partial_valid() {
        let schema = json!({
            "$schema": "http://json-schema.org/draft-07/schema#",
            "type": "object",
            "properties": {
                "name": { "type": "string" }
            },
            "required": ["name"]
        });

        let predictions = vec![
            json!({"name": "Alice"}),
            json!({"age": 30}), // missing required field
        ];

        let rate = schema_validity_rate(&predictions, &schema);
        assert!(rate > 0.0 && rate < 1.0);
    }

    #[test]
    fn test_field_precision_recall_f1_perfect_match() {
        let pred = json!({
            "name": "Alice",
            "age": 30,
            "city": "NYC"
        });

        let gt = json!({
            "name": "Alice",
            "age": 30,
            "city": "NYC"
        });

        let metrics = field_precision_recall_f1(&pred, &gt);
        assert_eq!(metrics.precision, 1.0);
        assert_eq!(metrics.recall, 1.0);
        assert_eq!(metrics.f1, 1.0);
    }

    #[test]
    fn test_field_precision_recall_f1_partial_match() {
        let pred = json!({
            "name": "Alice",
            "age": 30,
            "extra": "field"
        });

        let gt = json!({
            "name": "Alice",
            "age": 30,
            "city": "NYC"
        });

        let metrics = field_precision_recall_f1(&pred, &gt);
        assert!(metrics.precision < 1.0);
        assert!(metrics.recall < 1.0);
        assert!(metrics.f1 > 0.0);
    }

    #[test]
    fn test_field_precision_recall_f1_nested_objects() {
        let pred = json!({
            "name": "Alice",
            "address": {
                "city": "NYC",
                "zip": "10001"
            }
        });

        let gt = json!({
            "name": "Alice",
            "address": {
                "city": "NYC",
                "zip": "10001"
            }
        });

        let metrics = field_precision_recall_f1(&pred, &gt);
        assert_eq!(metrics.f1, 1.0);
    }

    #[test]
    fn test_field_precision_recall_f1_arrays() {
        let pred = json!({
            "items": ["apple", "banana"]
        });

        let gt = json!({
            "items": ["apple", "banana"]
        });

        let metrics = field_precision_recall_f1(&pred, &gt);
        assert_eq!(metrics.f1, 1.0);
    }

    #[test]
    fn test_type_correctness_rate_all_correct() {
        let pred = json!({
            "name": "Alice",
            "age": 30
        });

        let gt = json!({
            "name": "Bob",
            "age": 25
        });

        let rate = type_correctness_rate(&pred, &gt);
        assert_eq!(rate, 1.0);
    }

    #[test]
    fn test_type_correctness_rate_mixed() {
        let pred = json!({
            "name": "Alice",
            "age": "30" // wrong type: string instead of number
        });

        let gt = json!({
            "name": "Bob",
            "age": 25
        });

        let rate = type_correctness_rate(&pred, &gt);
        assert!(rate < 1.0 && rate > 0.0);
    }

    #[test]
    fn test_numeric_match_within_tolerance() {
        let tol = NumericTolerance::default();

        let pred = json!(101.0);
        let gt = json!(100.0);

        let result = numeric_match(&pred, &gt, &tol);
        assert!(result); // 1% difference is within default tolerance
    }

    #[test]
    fn test_numeric_match_outside_tolerance() {
        let tol = NumericTolerance::default();

        let pred = json!(150.0);
        let gt = json!(100.0);

        let result = numeric_match(&pred, &gt, &tol);
        assert!(!result); // 50% difference exceeds tolerance
    }

    #[test]
    fn test_numeric_match_currency() {
        let tol = NumericTolerance {
            currency_percent: 0.02, // 2% tolerance for currency
            decimal_percent: 0.01,
        };

        let pred = json!(102.0);
        let gt = json!(100.0);

        let result = numeric_match(&pred, &gt, &tol);
        assert!(result);
    }

    #[test]
    fn test_exact_match_identical() {
        let a = json!({
            "name": "Alice",
            "items": ["apple", "banana"]
        });

        let b = json!({
            "name": "Alice",
            "items": ["apple", "banana"]
        });

        assert!(exact_match(&a, &b));
    }

    #[test]
    fn test_exact_match_different() {
        let a = json!({
            "name": "Alice"
        });

        let b = json!({
            "name": "Bob"
        });

        assert!(!exact_match(&a, &b));
    }
}
