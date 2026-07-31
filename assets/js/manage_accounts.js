function openEditModal(userId, firstName, lastName, email, role, employeeType) {
    document.getElementById('editUserId').value = userId;
    document.getElementById('editFirstName').value = firstName;
    document.getElementById('editLastName').value = lastName;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = role;
    
    // Show/hide employee type based on role
    const employeeTypeGroup = document.getElementById('employeeTypeGroup');
    if (role === 'Employee') {
        employeeTypeGroup.style.display = 'block';
        document.getElementById('editEmployeeType').value = employeeType || 'Receptionist';
    } else {
        employeeTypeGroup.style.display = 'none';
    }
    
    document.getElementById('editModal').style.display = 'block';
}

function toggleEmployeeType() {
    const role = document.getElementById('editRole').value;
    const employeeTypeGroup = document.getElementById('employeeTypeGroup');
    employeeTypeGroup.style.display = role === 'Employee' ? 'block' : 'none';
}