#!/bin/bash

#######################################################################
# MQTT to Database Script (Bash)
# Receives MQTT data and stores it in MySQL database
#######################################################################

# Configuration
MQTT_BROKER="mqtt.iut-blagnac.fr"          # Change to your MQTT broker address
MQTT_PORT="1883"
MQTT_TOPIC="AM107/by-deviceName/+/data"        # Change to your MQTT topic

DB_HOST="localhost"
DB_USER="freezbee"          # Change to your MySQL username
DB_PASS="free"          # Change to your MySQL password  
DB_NAME="test"          # Change to your database name

# Log file
LOG_FILE="mqtt_to_db.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

#######################################################################
# Logging functions
#######################################################################

log() {
    local level="$1"
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo "[$timestamp] [$level] $message" | tee -a "$LOG_FILE"
    
    case "$level" in
        "ERROR")
            echo -e "${RED}[$timestamp] [$level] $message${NC}" >&2
            ;;
        "WARN")
            echo -e "${YELLOW}[$timestamp] [$level] $message${NC}"
            ;;
        "INFO")
            echo -e "${GREEN}[$timestamp] [$level] $message${NC}"
            ;;
        "DEBUG")
            echo -e "${BLUE}[$timestamp] [$level] $message${NC}"
            ;;
    esac
}

log_info() { log "INFO" "$@"; }
log_error() { log "ERROR" "$@"; }
log_warn() { log "WARN" "$@"; }
log_debug() { log "DEBUG" "$@"; }

#######################################################################
# Database functions
#######################################################################

db_query() {
    local query="$1"
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "$query" 2>/dev/null
}

db_query_result() {
    local query="$1"
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -se "$query" 2>/dev/null
}

check_db_connection() {
    log_info "Testing database connection..."
    if db_query "SELECT 1;" >/dev/null 2>&1; then
        log_info "Database connection successful"
        return 0
    else
        log_error "Database connection failed"
        return 1
    fi
}

ensure_building_exists() {
    local building_name="$1"
    
    # Check if building exists
    local building_id=$(db_query_result "SELECT id_batiment FROM Batiment WHERE nom = '$building_name';")
    
    if [[ -z "$building_id" ]]; then
        # Insert new building
        db_query "INSERT INTO Batiment (nom) VALUES ('$building_name');"
        if [[ $? -eq 0 ]]; then
            building_id=$(db_query_result "SELECT id_batiment FROM Batiment WHERE nom = '$building_name';")
            log_info "Added new building: $building_name (ID: $building_id)"
        else
            log_error "Failed to add building: $building_name"
            return 1
        fi
    fi
    
    echo "$building_id"
}

ensure_room_exists() {
    local room_name="$1"
    local building_id="$2"
    
    # Check if room exists
    local room_exists=$(db_query_result "SELECT nom_salle FROM Salle WHERE nom_salle = '$room_name';")
    
    if [[ -z "$room_exists" ]]; then
        # Insert new room with default values
        db_query "INSERT INTO Salle (nom_salle, id_batiment, type, capacite) VALUES ('$room_name', $building_id, 'standard', 0);"
        if [[ $? -eq 0 ]]; then
            log_info "Added new room: $room_name"
        else
            log_error "Failed to add room: $room_name"
            return 1
        fi
    fi
}

ensure_sensor_exists() {
    local device_name="$1"
    local sensor_type="$2"
    local unit="$3"
    local room_name="$4"
    
    local sensor_name="${device_name}_${sensor_type}"
    
    # Check if sensor exists
    local sensor_exists=$(db_query_result "SELECT nom_capteur FROM Capteur WHERE nom_capteur = '$sensor_name';")
    
    if [[ -z "$sensor_exists" ]]; then
        # Insert new sensor
        db_query "INSERT INTO Capteur (nom_capteur, type, unite, nom_salle) VALUES ('$sensor_name', '$sensor_type', '$unit', '$room_name');"
        if [[ $? -eq 0 ]]; then
            log_info "Added new sensor: $sensor_name"
        else
            log_error "Failed to add sensor: $sensor_name"
            return 1
        fi
    fi
}

ensure_sensors_exist() {
    local device_name="$1"
    local room_name="$2"
    
    ensure_sensor_exists "$device_name" "temperature" "°C" "$room_name"
    ensure_sensor_exists "$device_name" "humidity" "%" "$room_name"
    ensure_sensor_exists "$device_name" "co2" "ppm" "$room_name"
    ensure_sensor_exists "$device_name" "illumination" "lux" "$room_name"
}

insert_measurement() {
    local device_name="$1"
    local sensor_type="$2"
    local value="$3"
    
    local sensor_name="${device_name}_${sensor_type}"
    local date_str=$(date '+%Y-%m-%d')
    local time_str=$(date '+%H:%M:%S')
    
    # Insert measurement
    db_query "INSERT INTO Mesure (date, horaire, valeur, nom_capteur) VALUES ('$date_str', '$time_str', $value, '$sensor_name');"
    
    if [[ $? -eq 0 ]]; then
        log_debug "Inserted measurement: $sensor_name = $value"
    else
        log_error "Failed to insert measurement: $sensor_name = $value"
        return 1
    fi
}

#######################################################################
# JSON parsing functions
#######################################################################

extract_json_value() {
    local json="$1"
    local key="$2"
    
    # Extract value using grep and sed (basic JSON parsing)
    echo "$json" | grep -o "\"$key\"[[:space:]]*:[[:space:]]*[^,}]*" | sed 's/.*:[[:space:]]*//' | sed 's/[",]//g' | xargs
}

#######################################################################
# MQTT and data processing functions
#######################################################################

process_mqtt_message() {
    local payload="$1"
    
    log_debug "Processing MQTT payload: $payload"
    
    # Extract sensor data (first object)
    local sensor_data=$(echo "$payload" | sed -n '1,/},{/p' | sed 's/},{.*/}/')
    
    # Extract device info (second object) 
    local device_data=$(echo "$payload" | sed -n '/},{/,$p' | sed 's/.*},//')
    
    # Extract values from sensor data
    local temperature=$(extract_json_value "$sensor_data" "temperature")
    local humidity=$(extract_json_value "$sensor_data" "humidity")
    local co2=$(extract_json_value "$sensor_data" "co2")
    local illumination=$(extract_json_value "$sensor_data" "illumination")
    
    # Extract values from device data
    local device_name=$(extract_json_value "$device_data" "deviceName")
    local room=$(extract_json_value "$device_data" "room")
    local floor=$(extract_json_value "$device_data" "floor")
    local building=$(extract_json_value "$device_data" "Building")
    
    # Validate required fields
    if [[ -z "$device_name" || -z "$room" || -z "$building" ]]; then
        log_error "Missing required device information"
        return 1
    fi
    
    log_info "Processing data for device: $device_name, room: $room, building: $building"
    
    # Process database operations
    local building_id=$(ensure_building_exists "$building")
    if [[ -z "$building_id" ]]; then
        log_error "Failed to ensure building exists"
        return 1
    fi
    
    ensure_room_exists "$room" "$building_id"
    ensure_sensors_exist "$device_name" "$room"
    
    # Insert measurements (only if values are not empty)
    local measurements_count=0
    
    if [[ -n "$temperature" && "$temperature" != "null" ]]; then
        insert_measurement "$device_name" "temperature" "$temperature"
        ((measurements_count++))
    fi
    
    if [[ -n "$humidity" && "$humidity" != "null" ]]; then
        insert_measurement "$device_name" "humidity" "$humidity"
        ((measurements_count++))
    fi
    
    if [[ -n "$co2" && "$co2" != "null" ]]; then
        insert_measurement "$device_name" "co2" "$co2"
        ((measurements_count++))
    fi
    
    if [[ -n "$illumination" && "$illumination" != "null" ]]; then
        insert_measurement "$device_name" "illumination" "$illumination"
        ((measurements_count++))
    fi
    
    log_info "Successfully processed $measurements_count measurements for device $device_name"
}

#######################################################################
# MQTT client functions
#######################################################################

start_mqtt_client() {
    log_info "Starting MQTT client..."
    log_info "Broker: $MQTT_BROKER:$MQTT_PORT"
    log_info "Topic: $MQTT_TOPIC"
    
    # Build mosquitto_sub command
    local mqtt_cmd="mosquitto_sub -h $MQTT_BROKER -p $MQTT_PORT -t $MQTT_TOPIC"
    
    # Add authentication if configured
    if [[ -n "$MQTT_USER" ]]; then
        mqtt_cmd="$mqtt_cmd -u $MQTT_USER"
        if [[ -n "$MQTT_PASS" ]]; then
            mqtt_cmd="$mqtt_cmd -P $MQTT_PASS"
        fi
    fi
    
    log_info "MQTT command: ${mqtt_cmd//-P $MQTT_PASS/-P ***}"
    
    # Start MQTT subscription and process messages
    $mqtt_cmd | while IFS= read -r message; do
        if [[ -n "$message" ]]; then
            log_info "Received MQTT message"
            process_mqtt_message "$message"
        fi
    done
}

#######################################################################
# Utility functions
#######################################################################

check_dependencies() {
    log_info "Checking dependencies..."
    
    # Check for mosquitto_sub
    if ! command -v mosquitto_sub &> /dev/null; then
        log_error "mosquitto_sub not found. Please install mosquitto-clients:"
        log_error "  Ubuntu/Debian: sudo apt-get install mosquitto-clients"
        log_error "  CentOS/RHEL: sudo yum install mosquitto"
        log_error "  macOS: brew install mosquitto"
        return 1
    fi
    
    # Check for mysql
    if ! command -v mysql &> /dev/null; then
        log_error "mysql client not found. Please install mysql-client:"
        log_error "  Ubuntu/Debian: sudo apt-get install mysql-client"
        log_error "  CentOS/RHEL: sudo yum install mysql"
        log_error "  macOS: brew install mysql-client"
        return 1
    fi
    
    # Check for jq (optional but recommended)
    if ! command -v jq &> /dev/null; then
        log_warn "jq not found. JSON parsing will use basic sed/grep (less reliable)"
        log_warn "Consider installing jq: sudo apt-get install jq"
    fi
    
    log_info "Dependencies check completed"
}

cleanup() {
    log_info "Script interrupted. Cleaning up..."
    exit 0
}

show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -h, --help     Show this help message"
    echo "  -t, --test     Test database connection only"
    echo "  -v, --verbose  Enable verbose logging"
    echo ""
    echo "Configuration:"
    echo "  Edit the configuration variables at the top of this script"
    echo "  MQTT_BROKER, DB_HOST, DB_USER, etc."
}

#######################################################################
# Main function
#######################################################################

main() {
    local test_only=0
    local verbose=0
    
    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_usage
                exit 0
                ;;
            -t|--test)
                test_only=1
                shift
                ;;
            -v|--verbose)
                verbose=1
                shift
                ;;
            *)
                log_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done
    
    # Setup signal handlers
    trap cleanup SIGINT SIGTERM
    
    log_info "Starting MQTT to Database script (Bash version)"
    log_info "Log file: $LOG_FILE"
    
    # Check dependencies
    if ! check_dependencies; then
        exit 1
    fi
    
    # Test database connection
    if ! check_db_connection; then
        exit 1
    fi
    
    if [[ $test_only -eq 1 ]]; then
        log_info "Database test completed successfully"
        exit 0
    fi
    
    # Start MQTT client (this will block)
    log_info "Starting MQTT subscription..."
    start_mqtt_client
}

# Run main function with all arguments
main "$@"
